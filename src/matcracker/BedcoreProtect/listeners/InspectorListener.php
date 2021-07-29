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

        //It checks the block clicked
        if (Inspector::isInspector($player) && $this->config->isEnabledWorld($player->getLevelNonNull())) {
            $this->pluginQueries->requestBlockLog($player, $event->getBlock()->asPosition());
            $event->setCancelled();
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
        if (Inspector::isInspector($player) && $this->config->isEnabledWorld($player->getLevelNonNull())) {
            $this->pluginQueries->requestBlockLog($player, $event->getBlockReplaced()->asPosition());
            $event->setCancelled();
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
            $action = $event->getAction();

            if ($action === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
                if (BlockUtils::hasInventory($clickedBlock) || $clickedBlock instanceof ItemFrame) {
                    $tileChest = BlockUtils::asTile($clickedBlock->asPosition());
                    //This is needed for double chest to get the position of its holder (the left chest).
                    if ($tileChest instanceof Chest && ($holder = $tileChest->getInventory()->getHolder()) !== null) {
                        $position = $holder->asPosition();
                    } else {
                        $position = $clickedBlock->asPosition();
                    }
                    $this->pluginQueries->requestTransactionLog($player, $position);
                    $event->setCancelled();

                } elseif (BlockUtils::canBeClicked($clickedBlock)) {
                    $this->pluginQueries->requestBlockLog($player, $clickedBlock->asPosition());
                    $event->setCancelled();
                }
            }
        }
    }
}
