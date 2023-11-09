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
use pocketmine\block\Block;
use pocketmine\block\Sapling;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\block\LeavesDecayEvent;
use pocketmine\event\block\StructureGrowEvent;
use pocketmine\world\Position;

final class WorldListener extends BedcoreListener
{
    /**
     * @param LeavesDecayEvent $event
     *
     * @priority MONITOR
     */
    public function trackLeavesDecay(LeavesDecayEvent $event): void
    {
        $block = $event->getBlock();
        if ($this->config->isEnabledWorld($block->getPosition()->getWorld()) && $this->config->getLeavesDecay()) {
            $this->blocksQueries->addBlockLogByBlock($block, $block, VanillaBlocks::AIR(), Action::BREAK, $block->getPosition());
        }
    }

    /**
     * @param StructureGrowEvent $event
     *
     * @priority MONITOR
     */
    public function trackStructureGrowth(StructureGrowEvent $event): void
    {
        $block = $event->getBlock();
        $position = $block->getPosition();
        $world = $position->getWorld();

        if ($this->config->isEnabledWorld($world) && $this->config->getTreeGrowth()) {
            $player = $event->getPlayer();

            /** @var Position|null $sourcePos */
            $sourcePos = null;

            /**
             * @var int $x
             * @var int $y
             * @var int $z
             * @var Block $block
             */
            foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
                $replacedBlock = $world->getBlockAt($x, $y, $z);
                if ($player === null && $sourcePos === null && $replacedBlock instanceof Sapling) {
                    $sourcePos = $replacedBlock->getPosition();
                }

                //TODO: create function for log blocks transaction, this is too heavy. See explosions.
                $this->blocksQueries->addBlockLogByEntity($player, $replacedBlock, $block, Action::PLACE, $replacedBlock->getPosition(), $sourcePos);
            }
        }
    }
}
