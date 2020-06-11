<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
 *
 * Copyright (C) 2019
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author matcracker
 * @link https://www.github.com/matcracker/BedcoreProtect
 *
*/

declare(strict_types=1);

namespace matcracker\BedcoreProtect\listeners;

use InvalidStateException;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\serializable\SerializableBlock;
use matcracker\BedcoreProtect\utils\BlockUtils;
use pocketmine\block\Air;
use pocketmine\block\Bed;
use pocketmine\block\Block;
use pocketmine\block\Chest;
use pocketmine\block\Door;
use pocketmine\block\DoublePlant;
use pocketmine\block\Fallable;
use pocketmine\block\Lava;
use pocketmine\block\Liquid;
use pocketmine\block\Water;
use pocketmine\block\WaterLily;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\block\BlockFormEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\math\Vector3;
use pocketmine\scheduler\ClosureTask;
use pocketmine\tile\Chest as TileChest;
use function array_filter;
use function array_key_exists;
use function array_merge;
use function count;

final class BlockListener extends BedcoreListener
{
    /**
     * @param BlockBreakEvent $event
     *
     * @priority MONITOR
     * @ignoreCancelled
     */
    public function trackBlockBreak(BlockBreakEvent $event): void
    {
        $player = $event->getPlayer();
        $level = $player->getLevelNonNull();

        if ($this->config->isEnabledWorld($level) && $this->config->getBlockBreak()) {
            $block = $event->getBlock();

            if ($block instanceof Chest) {
                $tileChest = BlockUtils::asTile($block);
                if ($tileChest instanceof TileChest) {
                    $inventory = $tileChest->getRealInventory();
                    if (count($inventory->getContents()) > 0) {
                        $this->inventoriesQueries->addInventoryLogByPlayer($player, $inventory, $block->asPosition());
                    }
                }
            }

            $this->blocksQueries->addBlockLogByEntity($player, $block, $this->air, Action::BREAK(), $block->asPosition());

            if ($this->config->getNaturalBreak()) {
                $sides = $block->getAllSides();
                foreach ($sides as $side) {
                    foreach ($side->getAffectedBlocks() as $affectedSide) {
                        $sides = array_merge($sides, $affectedSide->getAllSides());
                    }
                }

                $sides = array_filter(
                    $sides,
                    static function (Block $block): bool {
                        return !$block instanceof Air;
                    }
                );

                $this->blocksQueries->addScheduledBlocksLogByEntity(
                    $player,
                    $sides,
                    Action::BREAK(),
                    function (array &$oldBlocks) use ($level, $sides) : array {
                        $newBlocks = [];
                        foreach ($sides as $key => $side) {
                            $updSide = $level->getBlock($side->asVector3());
                            if ($updSide instanceof $side) {
                                unset($oldBlocks[$key]);
                            } else {
                                $newBlocks[$key] = SerializableBlock::serialize($updSide);
                            }
                        }

                        return $newBlocks;
                    },
                    2
                );
            }
        }
    }

    /**
     * @param BlockPlaceEvent $event
     *
     * @priority MONITOR
     * @ignoreCancelled
     */
    public function trackBlockPlace(BlockPlaceEvent $event): void
    {
        $player = $event->getPlayer();
        $level = $player->getLevelNonNull();

        if ($this->config->isEnabledWorld($level) && $this->config->getBlockPlace()) {
            $replacedBlock = $event->getBlockReplaced();
            $block = $event->getBlock();

            if ($block instanceof Fallable) {
                $this->blocksQueries->addBlockLogByEntity($player, $replacedBlock, $block, Action::PLACE());
            } elseif ($block instanceof WaterLily && $replacedBlock instanceof Water) {
                $upPos = $block->getSide(Vector3::SIDE_UP);
                if ($upPos instanceof Air) {
                    $this->blocksQueries->addBlockLogByEntity($player, $this->air, $block, Action::PLACE(), $upPos->asPosition());
                }
            } else {
                //HACK: Remove when issue PMMP#1760 is fixed (never). Remember to use Block::getAffectedBlocks()
                $this->plugin->getScheduler()->scheduleDelayedTask(
                    new ClosureTask(
                        function (int $currentTick) use ($replacedBlock, $block, $player, $level) : void {
                            //Update the block instance to get the real placed block data.
                            $updBlock = $level->getBlock($block->asVector3());

                            /** @var Block|null $otherHalfBlock */
                            $otherHalfBlock = null;
                            if ($updBlock instanceof Bed) {
                                $otherHalfBlock = $updBlock->getOtherHalf();
                            } elseif ($updBlock instanceof Door || $updBlock instanceof DoublePlant) {
                                $otherHalfBlock = $updBlock->getSide(Vector3::SIDE_UP);
                            }

                            if ($updBlock instanceof $block) { //HACK: Fixes issue #9 (always related to PMMP#1760)
                                $this->blocksQueries->addBlockLogByEntity($player, $replacedBlock, $updBlock, Action::PLACE());

                                if ($otherHalfBlock !== null) {
                                    $this->blocksQueries->addBlockLogByEntity($player, $replacedBlock, $otherHalfBlock, Action::PLACE());
                                }
                            }
                        }
                    ),
                    1
                );
            }
        }
    }

    /**
     * @param BlockSpreadEvent $event
     *
     * @priority MONITOR
     * @ignoreCancelled
     */
    public function trackBlockSpread(BlockSpreadEvent $event): void
    {
        $block = $event->getBlock();
        $source = $event->getSource();

        if ($this->config->isEnabledWorld($block->getLevelNonNull())) {
            if ($source instanceof Liquid) {
                $action = !$block instanceof Air ? Action::BREAK() : Action::PLACE();
                $this->blocksQueries->addBlockLogByBlock($source, $block, $source, $action, $block->asPosition());
            }
        }
    }

    /**
     * @param BlockBurnEvent $event
     *
     * @priority MONITOR
     * @ignoreCancelled
     */
    public function trackBlockBurn(BlockBurnEvent $event): void
    {
        $block = $event->getBlock();
        if ($this->config->isEnabledWorld($block->getLevelNonNull()) && $this->config->getBlockBurn()) {
            $this->blocksQueries->addBlockLogByBlock($event->getCausingBlock(), $block, $this->air, Action::BREAK(), $block->asPosition());
        }
    }

    /**
     * @param BlockFormEvent $event
     *
     * @priority MONITOR
     * @ignoreCancelled
     */
    public function trackBlockForm(BlockFormEvent $event): void
    {
        $block = $event->getBlock();

        if ($this->config->isEnabledWorld($block->getLevelNonNull())) {
            if ($block instanceof Liquid && $this->config->getLiquidTracking()) {
                $result = $event->getNewState();

                $liquid = $block instanceof Water ? new Lava() : new Water();
                $this->blocksQueries->addBlockLogByBlock($liquid, $block, $result, Action::PLACE(), $block->asPosition());
            }
        }
    }

    /**
     * @param SignChangeEvent $event
     *
     * @priority MONITOR
     * @ignoreCancelled
     */
    public function trackSignChange(SignChangeEvent $event): void
    {
        $block = $event->getBlock();

        if ($this->config->isEnabledWorld($block->getLevelNonNull()) && $this->config->getSignText()) {
            $this->blocksQueries->addBlockLogByEntity($event->getPlayer(), $block, $block, Action::UPDATE());
        }
    }
}
