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

use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\utils\BlockUtils;
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
use pocketmine\math\Vector3;
use pocketmine\scheduler\ClosureTask;
use pocketmine\tile\Chest as TileChest;
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

            if ($block instanceof Door) {
                $top = $block->getDamage() & 0x08;
                $other = $block->getSide($top ? Vector3::SIDE_DOWN : Vector3::SIDE_UP);
                if ($other instanceof Door) {
                    $this->blocksQueries->addBlockLogByEntity($player, $other, $this->air, Action::BREAK(), $other->asPosition());
                }
            } elseif ($block instanceof Bed) {
                $other = $block->getOtherHalf();
                if ($other !== null) {
                    $this->blocksQueries->addBlockLogByEntity($player, $other, $this->air, Action::BREAK(), $other->asPosition());
                }
            } elseif ($block instanceof Chest) {
                $tileChest = BlockUtils::asTile($block);
                if ($tileChest instanceof TileChest) {
                    $inventory = $tileChest->getRealInventory();
                    if (count($inventory->getContents()) > 0) {
                        $this->inventoriesQueries->addInventoryLogByPlayer($player, $inventory, $block->asPosition());
                    }
                }
            }

            if ($this->config->getNaturalBreak()) {
                $sides = $block->getAllSides();
                $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(
                    function (int $currentTick) use ($player, $block, $level, $sides): void {
                        $updSides = $level->getBlock($block->asVector3())->getAllSides();

                        for ($i = 0, $maxI = count($updSides); $i < $maxI; $i++) {
                            if (!($updSides[$i] instanceof $sides[$i])) {
                                $this->blocksQueries->addBlockLogByEntity($player, $sides[$i], $this->air, Action::BREAK(), $sides[$i]->asPosition());
                            }
                        }
                    }
                ), 2);
            }

            $this->blocksQueries->addBlockLogByEntity($player, $block, $this->air, Action::BREAK(), $block->asPosition());
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
                $upPos = $block->getSide(Vector3::SIDE_UP)->asPosition();
                $this->blocksQueries->addBlockLogByEntity($player, $this->air, $block, Action::PLACE(), $upPos);
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

            if ($source instanceof Liquid && $source->getId() === $source->getStillForm()->getId()) {
                $this->blocksQueries->addBlockLogByBlock($source, $block, $source, Action::PLACE());
        if ($this->config->isEnabledWorld($block->getLevelNonNull())) {
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
}
