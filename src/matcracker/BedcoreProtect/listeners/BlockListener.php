<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
 *
 * Copyright (C) 2019-2021
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
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\Liquid;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Water;
use pocketmine\block\WaterLily;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\block\BlockFormEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\inventory\InventoryHolder;
use pocketmine\math\Facing;
use function array_merge;
use function count;

final class BlockListener extends BedcoreListener
{
    /**
     * @param BlockBreakEvent $event
     *
     * @priority MONITOR
     */
    public function trackBlockBreak(BlockBreakEvent $event): void
    {
        $player = $event->getPlayer();
        $world = $player->getWorld();

        if ($this->config->isEnabledWorld($world) && $this->config->getBlockBreak()) {
            $block = $event->getBlock();
            $blockPos = $block->getPosition();
            $blockTile = BlockUtils::asTile($blockPos);
            if ($blockTile instanceof InventoryHolder) {
                $inventory = $blockTile->getInventory();
                if (count($inventory->getContents()) > 0) {
                    $this->inventoriesQueries->addInventoryLogByPlayer($player, $inventory, $blockPos);
                }
            }

            $this->blocksQueries->addBlockLogByEntity($player, $block, VanillaBlocks::AIR(), Action::BREAK(), $blockPos);

            if ($this->config->getNaturalBreak()) {
                $sides = [];
                foreach ($block->getAllSides() as $side) {
                    if (!$side instanceof Air) {
                        $sides = array_merge($sides, $side->getAffectedBlocks());
                    }
                }

                $this->blocksQueries->addScheduledBlocksLogByEntity(
                    $player,
                    $sides,
                    Action::BREAK(),
                    static function (array &$oldBlocks, array &$oldBlocksNbt) use ($world, $sides): array {
                        $newBlocks = [];
                        foreach ($sides as $key => $side) {
                            $updSide = $world->getBlock($side->getPosition());
                            if ($updSide->isSameType($side)) {
                                unset($oldBlocks[$key], $oldBlocksNbt[$key]);
                            } else {
                                $newBlocks[$key] = $updSide;
                            }
                        }

                        return $newBlocks;
                    },
                    1
                );
            }
        }
    }

    /**
     * @param BlockPlaceEvent $event
     *
     * @priority MONITOR
     */
    public function trackBlockPlace(BlockPlaceEvent $event): void
    {
        $player = $event->getPlayer();
        $world = $player->getWorld();

        if ($this->config->isEnabledWorld($world) && $this->config->getBlockPlace()) {
            /**
             * @var int $x
             * @var int $y
             * @var int $z
             * @var Block $block
             */
            foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
                $replacedBlock = $world->getBlockAt($x, $y, $z);

                if ($block instanceof WaterLily && $replacedBlock instanceof Water) {
                    $upPos = $block->getSide(Facing::UP);
                    if ($upPos instanceof Air) {
                        $this->blocksQueries->addBlockLogByEntity($player, VanillaBlocks::AIR(), $block, Action::PLACE(), $upPos->getPosition());
                    }
                } else {
                    $this->blocksQueries->addBlockLogByEntity($player, $replacedBlock, $block, Action::PLACE());
                }
            }
        }
    }

    /**
     * @param BlockSpreadEvent $event
     *
     * @priority MONITOR
     */
    public function trackBlockSpread(BlockSpreadEvent $event): void
    {
        $block = $event->getBlock();
        $blockPos = $block->getPosition();
        $source = $event->getSource();

        if ($this->config->isEnabledWorld($blockPos->getWorld())) {
            if ($source instanceof Liquid) {
                $action = $block instanceof Air ? Action::PLACE() : Action::BREAK();
                $this->blocksQueries->addBlockLogByBlock($source, $block, $source, $action, $blockPos);
            }
        }
    }

    /**
     * @param BlockBurnEvent $event
     *
     * @priority MONITOR
     */
    public function trackBlockBurn(BlockBurnEvent $event): void
    {
        $block = $event->getBlock();
        $blockPos = $block->getPosition();

        if ($this->config->isEnabledWorld($blockPos->getWorld()) && $this->config->getBlockBurn()) {
            $this->blocksQueries->addBlockLogByBlock($event->getCausingBlock(), $block, VanillaBlocks::AIR(), Action::BREAK(), $blockPos);
        }
    }

    /**
     * @param BlockFormEvent $event
     *
     * @priority MONITOR
     */
    public function trackBlockForm(BlockFormEvent $event): void
    {
        $block = $event->getBlock();
        $blockPos = $block->getPosition();

        if ($this->config->isEnabledWorld($blockPos->getWorld())) {
            if ($block instanceof Liquid && $this->config->getLiquidTracking()) {
                $result = $event->getNewState();

                $liquid = $block instanceof Water ? VanillaBlocks::LAVA() : VanillaBlocks::WATER();
                $this->blocksQueries->addBlockLogByBlock($liquid, $block, $result, Action::PLACE(), $blockPos);
            }
        }
    }

    /**
     * @param SignChangeEvent $event
     *
     * @priority MONITOR
     */
    public function trackSignChange(SignChangeEvent $event): void
    {
        $block = $event->getBlock();

        if ($this->config->isEnabledWorld($block->getPosition()->getWorld()) && $this->config->getSignText()) {
            $this->blocksQueries->addBlockLogByEntity($event->getPlayer(), $block, $block, Action::UPDATE());
        }
    }
}
