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

use matcracker\BedcoreProtect\Inspector;
use matcracker\BedcoreProtect\utils\BlockUtils;
use pocketmine\block\ItemFrame;
use pocketmine\block\tile\Chest;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;

final class InspectorListener extends BedcoreListener
{
    /**
     * @param PlayerQuitEvent $event
     *
     * @priority MONITOR
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

        //It checks the block clicked
        if (Inspector::isInspector($player) && $this->config->isEnabledWorld($player->getWorld())) {
            $this->pluginQueries->requestBlockLog($player, $event->getBlock()->getPosition());
            $event->cancel();
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

        //It checks the block where the player places.
        if (Inspector::isInspector($player) && $this->config->isEnabledWorld($player->getWorld())) {
            $this->pluginQueries->requestBlockLog($player, $event->getBlockReplaced()->getPosition());
            $event->cancel();
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

        if (Inspector::isInspector($player) && $this->config->isEnabledWorld($player->getWorld())) {
            $clickedBlock = $event->getBlock();
            $action = $event->getAction();

            if ($action === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
                if (BlockUtils::hasInventory($clickedBlock) || $clickedBlock instanceof ItemFrame) {
                    $tileChest = BlockUtils::asTile($clickedBlock->getPosition());
                    //This is needed for double chest to get the position of its holder (the left chest).
                    if ($tileChest instanceof Chest) {
                        $position = $tileChest->getInventory()->getHolder();
                    } else {
                        $position = $clickedBlock->getPosition();
                    }
                    $this->pluginQueries->requestTransactionLog($player, $position);
                    $event->cancel();

                } elseif (BlockUtils::canBeClicked($clickedBlock)) {
                    $this->pluginQueries->requestBlockLog($player, $clickedBlock->getPosition());
                    $event->cancel();
                }
            }
        }
    }
}
