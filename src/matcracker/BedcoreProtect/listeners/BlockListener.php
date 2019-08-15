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
use matcracker\BedcoreProtect\Inspector;
use matcracker\BedcoreProtect\utils\BlockUtils;
use pocketmine\block\Bed;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIds;
use pocketmine\block\Chest;
use pocketmine\block\Door;
use pocketmine\block\Liquid;
use pocketmine\block\Water;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\block\BlockFormEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\math\Vector3;
use pocketmine\tile\Chest as TileChest;

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
        if ($this->plugin->getParsedConfig()->isEnabledWorld($player->getLevel()) && $this->plugin->getParsedConfig()->getBlockBreak()) {
            $block = $event->getBlock();

            if (Inspector::isInspector($player)) { //It checks the block clicked
                $this->database->getQueries()->requestBlockLog($player, $block);
                $event->setCancelled();
            } else {
                $air = BlockFactory::get(BlockIds::AIR);

                if ($block instanceof Door) {
                    $top = $block->getDamage() & 0x08;
                    $other = $block->getSide($top ? Vector3::SIDE_DOWN : Vector3::SIDE_UP);
                    if ($other instanceof Door and $other->getId() === $block->getId()) {
                        $this->database->getQueries()->addBlockLogByEntity($player, $other, $air, Action::BREAK(), $other->asPosition());
                    }
                } elseif ($block instanceof Bed) {
                    $other = $block->getOtherHalf();
                    if ($other instanceof Bed) {
                        $this->database->getQueries()->addBlockLogByEntity($player, $other, $air, Action::BREAK(), $other->asPosition());
                    }
                } elseif ($block instanceof Chest) {
                    $tileChest = BlockUtils::asTile($block);
                    if ($tileChest instanceof TileChest) {
                        $inventory = $tileChest->getRealInventory();
                        if (!empty($inventory->getContents())) {
                            $this->database->getQueries()->addInventoryLogByPlayer($player, $inventory, $block->asPosition());
                        }
                    }
                }

                $this->database->getQueries()->addBlockLogByEntity($player, $block, $air, Action::BREAK(), $block->asPosition());

                if ($this->plugin->getParsedConfig()->getNaturalBreak()) {
                    /**
                     * @var Block[] $sides
                     * Getting all blocks around the broken block that are consequently destroyed.
                     */
                    $sides = array_filter($block->getAllSides(), static function (Block $side) {
                        return $side->canBePlaced() && !$side->isSolid() && $side->isTransparent();
                    });

                    if (!empty($sides)) {
                        $this->database->getQueries()->addBlocksLogByEntity($player, $sides, $air, Action::BREAK());
                    }
                }
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
        if ($this->plugin->getParsedConfig()->isEnabledWorld($player->getLevel()) && $this->plugin->getParsedConfig()->getBlockPlace()) {
            $block = $event->getBlock();
            $replacedBlock = $event->getBlockReplaced();

            if (Inspector::isInspector($player)) { //It checks the block where the player places.
                $this->database->getQueries()->requestBlockLog($player, $replacedBlock);
                $event->setCancelled();
            } else {
                $otherHalfPos = null;
                if ($block instanceof Bed) {
                    $meta = ($player->getDirection() - 1) & 0x03;
                    $otherHalfPos = $block->getSide(Bed::getOtherHalfSide($meta))->asPosition();
                } else if ($block instanceof Door) {
                    $otherHalfPos = $block->getSide(Vector3::SIDE_UP)->asPosition();
                }

                $this->database->getQueries()->addBlockLogByEntity($player, $replacedBlock, $block, Action::PLACE());

                if ($otherHalfPos !== null) {
                    $this->database->getQueries()->addBlockLogByEntity($player, $replacedBlock, $block, Action::PLACE(), $otherHalfPos);
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
        $source = $event->getSource();

        if ($this->plugin->getParsedConfig()->isEnabledWorld($block->getLevel())) {
            if ($source instanceof Liquid && $source->getId() === $source->getStillForm()->getId()) {
                $this->database->getQueries()->addBlockLogByBlock($source, $block, $source, Action::PLACE());
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
        if ($this->plugin->getParsedConfig()->isEnabledWorld($block->getLevel()) && $this->plugin->getParsedConfig()->getBlockBurn()) {
            $cause = $event->getCausingBlock();

            $this->database->getQueries()->addBlockLogByBlock($cause, $block, $cause, Action::BREAK());
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
        $result = $event->getNewState();

        if ($this->plugin->getParsedConfig()->isEnabledWorld($block->getLevel())) {
            if ($block instanceof Liquid && $this->plugin->getParsedConfig()->getLiquidTracking()) {
                $liquid = $block instanceof Water ? BlockFactory::get(BlockIds::FLOWING_LAVA) : BlockFactory::get(BlockIds::FLOWING_WATER);
                $this->database->getQueries()->addBlockLogByBlock($liquid, $block, $result, Action::PLACE(), $block->asPosition());
            }
        }
    }

}