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
use pocketmine\block\Air;
use pocketmine\block\BlockFactory;
use pocketmine\block\Fire;
use pocketmine\block\ItemFrame;
use pocketmine\block\TNT;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerBucketEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\ContainerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\FlintSteel;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\tile\Chest;
use pocketmine\tile\ItemFrame as ItemFrameTile;
use UnexpectedValueException;

final class PlayerListener extends BedcoreListener
{
    /**
     * @param PlayerJoinEvent $event
     *
     * @priority HIGHEST
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
            $liquid = BlockFactory::get($bucketMeta);

            if ($fireEmptyEvent) {
                $this->blocksQueries->addBlockLogByEntity($player, $block, $liquid, Action::PLACE(), $block->asPosition());
            } else {
                $face = $event->getBlockFace();
                switch ($face) {
                    case Vector3::SIDE_DOWN:
                        $liquidPos = $block->add(0, 1, 0);
                        break;
                    case Vector3::SIDE_UP:
                        $liquidPos = $block->add(0, -1, 0);
                        break;
                    case Vector3::SIDE_NORTH:
                        $liquidPos = $block->add(0, 0, 1);
                        break;
                    case Vector3::SIDE_SOUTH:
                        $liquidPos = $block->add(0, 0, -1);
                        break;
                    case Vector3::SIDE_WEST:
                        $liquidPos = $block->add(1, 0, 0);
                        break;
                    case Vector3::SIDE_EAST:
                        $liquidPos = $block->add(-1, 0, 0);
                        break;
                    default:
                        throw new UnexpectedValueException("Unrecognized block face (Value: {$face}).");
                }

                $this->blocksQueries->addBlockLogByEntity($player, $liquid, $block, Action::BREAK(), Position::fromObject($liquidPos, $block->getLevel()));
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
        $config = $this->plugin->getParsedConfig();

        if ($config->isEnabledWorld($player->getLevel())) {
            $clickedBlock = $event->getBlock();
            $itemInHand = $event->getItem();
            $leftClickBlock = $event->getAction() === PlayerInteractEvent::LEFT_CLICK_BLOCK;
            $rightClickBlock = $event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK;

            if (Inspector::isInspector($player)) {
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

                //This check must be done due to allow BlockPlaceEvent to be fired (second "OR" condition)
                } elseif ($leftClickBlock || ($rightClickBlock && !$itemInHand->canBePlaced())) {
                    $this->pluginQueries->requestBlockLog($player, $clickedBlock);
                    $event->setCancelled();
                }

                return;
            }

            if (!$event->isCancelled()) {
                if ($leftClickBlock || $rightClickBlock) {
                    $replacedBlock = $clickedBlock->getSide($event->getFace());
                    if ($leftClickBlock) {
                        if ($config->getBlockBreak() && $replacedBlock instanceof Fire) {
                            $this->blocksQueries->addBlockLogByEntity($player, $replacedBlock, $this->air, Action::BREAK(), $replacedBlock->asPosition());
                            return;
                        }
                    } else { //Right click
                        if ($config->getBlockPlace() && $itemInHand instanceof FlintSteel && $replacedBlock instanceof Air) {
                            if ($clickedBlock instanceof TNT) {
                                $this->blocksQueries->addBlockLogByEntity($player, $clickedBlock, $this->air, Action::BREAK(), $clickedBlock->asPosition());
                            } else {
                                $this->blocksQueries->addBlockLogByEntity($player, $this->air, new Fire(), Action::PLACE(), $replacedBlock->asPosition());
                            }
                            return;
                        }
                    }

                    if ($config->getPlayerInteractions() && BlockUtils::canBeClicked($clickedBlock)) {
                        if ($clickedBlock instanceof ItemFrame) {
                            $tile = BlockUtils::asTile($clickedBlock);
                            if ($tile instanceof ItemFrameTile) {
                                $oldNbt = BlockUtils::getCompoundTag($clickedBlock);
                                //I consider the ItemFrame as a fake inventory holder to only log "adding/removing" item.
                                if (!$tile->hasItem() && !$itemInHand->isNull()) {
                                    $this->blocksQueries->addItemFrameLogByPlayer($player, $clickedBlock, $oldNbt, Action::ADD());
                                    return;
                                } elseif ($tile->hasItem() && $leftClickBlock) {
                                    $this->blocksQueries->addItemFrameLogByPlayer($player, $clickedBlock, $oldNbt, Action::REMOVE());
                                    return;
                                }
                            }
                        }

                        if (!$clickedBlock instanceof Air) {
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
