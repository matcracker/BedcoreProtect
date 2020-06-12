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

use matcracker\BedcoreProtect\Inspector;
use matcracker\BedcoreProtect\utils\BlockUtils;
use pocketmine\block\ItemFrame;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\tile\Chest;

final class InspectorListener extends BedcoreListener
{
    /**
     * @param PlayerQuitEvent $event
     *
     * @priority LOWEST
     */
    public function onInspectorQuit(PlayerQuitEvent $event): void
    {
        Inspector::removeInspector($event->getPlayer());
    }

    /**
     * @param BlockBreakEvent $event
     *
     * @priority HIGHEST
     */
    public function onInspectBlockBreak(BlockBreakEvent $event): void
    {
        $player = $event->getPlayer();

        if ($this->config->isEnabledWorld($player->getLevelNonNull())) {
            if (Inspector::isInspector($player)) { //It checks the block clicked
                $this->pluginQueries->requestBlockLog($player, $event->getBlock());
                $event->setCancelled();
            }
        }
    }

    /**
     * @param BlockPlaceEvent $event
     *
     * @priority HIGHEST
     */
    public function onInspectBlockPlace(BlockPlaceEvent $event): void
    {
        $player = $event->getPlayer();

        if ($this->config->isEnabledWorld($player->getLevelNonNull())) {
            if (Inspector::isInspector($player)) { //It checks the block where the player places.
                $this->pluginQueries->requestBlockLog($player, $event->getBlockReplaced());
                $event->setCancelled();
            }
        }
    }

    /**
     * @param PlayerInteractEvent $event
     *
     * @priority HIGHEST
     */
    public function onInspectBlockInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();

        if (Inspector::isInspector($player) && $this->config->isEnabledWorld($player->getLevelNonNull())) {
            $clickedBlock = $event->getBlock();

            if (BlockUtils::hasInventory($clickedBlock) || $clickedBlock instanceof ItemFrame) {
                $position = $clickedBlock->asPosition();
                $tileChest = BlockUtils::asTile($clickedBlock);
                if ($tileChest instanceof Chest) { //This is needed for double chest to get the position of its holder (the left chest).
                    $holder = $tileChest->getInventory()->getHolder();
                    if ($holder !== null) {
                        $position = $holder->asPosition();
                    }
                }
                $this->pluginQueries->requestTransactionLog($player, $position);
                $event->setCancelled();

            } elseif (BlockUtils::canBeClicked($clickedBlock)) {
                $this->pluginQueries->requestBlockLog($player, $clickedBlock);
                $event->setCancelled();
            }
        }
    }
}
