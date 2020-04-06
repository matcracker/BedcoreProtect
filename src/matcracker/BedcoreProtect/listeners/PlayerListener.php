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
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIds;
use pocketmine\block\ItemFrame;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerBucketEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\ContainerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\ItemIds;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\tile\Chest;
use pocketmine\tile\ItemFrame as ItemFrameTile;

final class PlayerListener extends BedcoreListener
{
    /**
     * @param PlayerJoinEvent $event
     *
     * @priority LOWEST
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        $this->entitiesQueries->addEntity($event->getPlayer());
    }

    /**
     * @param PlayerQuitEvent $event
     *
     * @priority LOWEST
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        Inspector::removeInspector($event->getPlayer());
    }

    /**
     * @param PlayerBucketEvent $event
     *
     * @priority MONITOR
     */
    public function trackPlayerBucket(PlayerBucketEvent $event): void
    {
        $player = $event->getPlayer();
        if ($this->plugin->getParsedConfig()->isEnabledWorld($player->getLevel()) && $this->plugin->getParsedConfig()->getBuckets()) {
            $block = $event->getBlockClicked();
            $fireEmptyEvent = ($event instanceof PlayerBucketEmptyEvent);

            $bucketMeta = $fireEmptyEvent ? $event->getBucket()->getDamage() : $event->getItem()->getDamage();

            $liquid = BlockFactory::get(BlockIds::FLOWING_WATER);
            if ($bucketMeta === BlockIds::FLOWING_LAVA) {
                $liquid = BlockFactory::get(BlockIds::FLOWING_LAVA);
            }

            if ($fireEmptyEvent) {
                $this->blocksQueries->addBlockLogByEntity($player, $block, $liquid, Action::PLACE(), $block->asPosition());
            } else {
                $liquidPos = null;
                $face = $event->getBlockFace();
                if ($face === Vector3::SIDE_DOWN) {
                    $liquidPos = Position::fromObject($block->add(0, 1, 0), $block->getLevel());
                } elseif ($face === Vector3::SIDE_UP) {
                    $liquidPos = Position::fromObject($block->subtract(0, 1, 0), $block->getLevel());
                }

                $this->blocksQueries->addBlockLogByEntity($player, $liquid, $block, Action::BREAK(), $liquidPos);
            }
        }
    }

    /**
     * @param PlayerInteractEvent $event
     *
     * @priority MONITOR
     */
    public function trackPlayerInteraction(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();

        if ($this->plugin->getParsedConfig()->isEnabledWorld($player->getLevel())) {
            $clickedBlock = $event->getBlock();
            $itemInHand = $event->getItem();
            $action = $event->getAction();
            $face = $event->getFace();

            if (Inspector::isInspector($player)) {
                if (BlockUtils::hasInventory($clickedBlock) || $clickedBlock instanceof ItemFrame) {
                    $event->setCancelled();
                    $position = $clickedBlock->asPosition();
                    $tileChest = BlockUtils::asTile($clickedBlock);
                    if ($tileChest instanceof Chest) { //This is needed for double chest to get the position of its holder (the left chest).
                        $holder = $tileChest->getInventory()->getHolder();
                        if ($holder !== null) {
                            $position = $holder->asPosition();
                        }
                    }
                    $this->pluginQueries->requestTransactionLog($player, $position);
                }

                return;
            }

            if (!$event->isCancelled()) {
                if ($action === PlayerInteractEvent::LEFT_CLICK_BLOCK || $action === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
                    if ($action === PlayerInteractEvent::LEFT_CLICK_BLOCK) {
                        $relativeBlock = $clickedBlock->getSide($face);
                        if ($this->plugin->getParsedConfig()->getBlockBreak() && $relativeBlock->getId() === BlockIds::FIRE) {
                            $this->blocksQueries->addBlockLogByEntity($player, $relativeBlock, BlockFactory::get(BlockIds::AIR), Action::BREAK(), $relativeBlock->asPosition());
                            return;
                        }
                    } elseif ($action === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
                        if ($this->plugin->getParsedConfig()->getBlockPlace() && $itemInHand->getId() === ItemIds::FLINT_AND_STEEL) {
                            $this->blocksQueries->addBlockLogByEntity($player, BlockFactory::get(BlockIds::AIR), BlockFactory::get(BlockIds::FIRE), Action::PLACE(), $clickedBlock->getSide($face)->asPosition());
                            return;
                        }
                    }

                    if ($this->plugin->getParsedConfig()->getPlayerInteractions() && BlockUtils::canBeClicked($clickedBlock)) {
                        if ($clickedBlock instanceof ItemFrame) {
                            $tile = BlockUtils::asTile($clickedBlock);
                            if ($tile instanceof ItemFrameTile) {
                                $oldNbt = BlockUtils::getCompoundTag($clickedBlock);
                                //I consider the ItemFrame as a fake inventory holder to only log "adding/removing" item.
                                if (!$tile->hasItem() && !$itemInHand->isNull()) {
                                    $this->blocksQueries->addItemFrameLogByPlayer($player, $clickedBlock, $oldNbt, Action::ADD());
                                    return;
                                } elseif ($tile->hasItem() && $action === PlayerInteractEvent::LEFT_CLICK_BLOCK) {
                                    $this->blocksQueries->addItemFrameLogByPlayer($player, $clickedBlock, $oldNbt, Action::REMOVE());
                                    return;
                                }
                            }
                        }

                        if ($clickedBlock->getId() !== BlockIds::AIR) {
                            $this->blocksQueries->addBlockLogByEntity($player, $clickedBlock, $clickedBlock, Action::CLICK());
                        }
                    }
                }
            }
        }
    }

    /**
     * @param InventoryTransactionEvent $event
     *
     * @priority MONITOR
     */
    public function trackInventoryTransaction(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();

        if ($this->plugin->getParsedConfig()->isEnabledWorld($player->getLevel()) && $this->plugin->getParsedConfig()->getItemTransactions()) {
            $actions = $transaction->getActions();

            foreach ($actions as $action) {
                if ($action instanceof SlotChangeAction && $action->getInventory() instanceof ContainerInventory) {
                    $this->inventoriesQueries->addInventorySlotLogByPlayer($player, $action);
                    break;
                }
            }
        }
    }
}
