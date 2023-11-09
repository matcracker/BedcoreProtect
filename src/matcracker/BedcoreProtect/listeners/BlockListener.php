<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
 *
 * Copyright (C) 2019-2023
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
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Fire;
use pocketmine\block\Liquid;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Water;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\block\BlockFormEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\block\SignChangeEvent;
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

            $affectedBlocks = $block->getAffectedBlocks();

            $this->blocksQueries->addExplosionLogByEntity($player, $affectedBlocks, Action::BREAK);

            if ($this->config->getNaturalBreak()) {
                $sides = [];
                foreach ($block->getAllSides() as $side) {
                    if ($side->getTypeId() === BlockTypeIds::AIR) {
                        continue;
                    }
                    if (count($block->getAffectedBlocks()) > 1) {
                        foreach ($block->getAffectedBlocks() as $affectedBlock) {
                            if ($affectedBlock->getPosition()->equals($side->getPosition())) {
                                continue 2;
                            }
                        }
                    }

                    $sides = array_merge($sides, $side->getAffectedBlocks());
                }

                //This is necessary because it is not possible to predict which blocks will be broken
                $this->blocksQueries->addScheduledBlocksLogByEntity($player, $sides, Action::BREAK, 2);
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
                $this->blocksQueries->addBlockLogByEntity($player, $replacedBlock, $block, Action::PLACE);
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

        if ($this->config->isEnabledWorld($blockPos->getWorld())) {
            $newState = $event->getNewState();
            if ($newState instanceof Liquid) {
                $action = $block instanceof Air ? Action::PLACE : Action::BREAK;

                $flowVector = $newState->getFlowVector()->floor();
                //In case of vertical falling water we need to point to the replaced block position
                if ($flowVector->getY() !== 0) {
                    $sourcePos = $blockPos->subtractVector($flowVector);
                } else {
                    $sourcePos = $newState->getPosition()->subtractVector($flowVector);
                }

                $this->blocksQueries->addBlockLogByBlock($newState, $block, $newState, $action, $blockPos, $sourcePos);
            } elseif ($newState instanceof Fire) {
                $this->blocksQueries->addBlockLogByBlock($newState, $block, $newState, Action::PLACE, $blockPos, $newState->getPosition());
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
            $causingBlock = $event->getCausingBlock();
            $this->blocksQueries->addBlockLogByBlock($causingBlock, $block, VanillaBlocks::AIR(), Action::BREAK, $blockPos, $causingBlock->getPosition());
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

                $this->blocksQueries->addBlockLogByBlock($liquid, $block, $result, Action::PLACE, $blockPos, $blockPos);
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
            $this->blocksQueries->addBlockLogByEntity($event->getPlayer(), $block, $block, Action::UPDATE);
        }
    }
}
