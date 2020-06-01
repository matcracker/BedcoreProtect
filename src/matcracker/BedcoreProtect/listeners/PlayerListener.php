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
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\Air;
use pocketmine\block\BlockFactory;
use pocketmine\block\Dirt;
use pocketmine\block\Farmland;
use pocketmine\block\Fire;
use pocketmine\block\Grass;
use pocketmine\block\GrassPath;
use pocketmine\block\ItemFrame;
use pocketmine\block\TNT;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerBucketEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\inventory\ContainerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\FlintSteel;
use pocketmine\item\Hoe;
use pocketmine\item\Shovel;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\tile\ItemFrame as TileItemFrame;
use UnexpectedValueException;

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
     * @param PlayerBucketEvent $event
     *
     * @priority MONITOR
     * @ignoreCancelled
     */
    public function trackPlayerBucket(PlayerBucketEvent $event): void
    {
        $player = $event->getPlayer();
        if ($this->config->isEnabledWorld(Utils::getLevelNonNull($player->getLevel())) && $this->config->getBuckets()) {
            $block = $event->getBlockClicked();
            $fireEmptyEvent = $event instanceof PlayerBucketEmptyEvent;

            $bucketMeta = $fireEmptyEvent ? $event->getBucket()->getDamage() : $event->getItem()->getDamage();
            $liquid = BlockFactory::get($bucketMeta);

            if ($fireEmptyEvent) {
                if (!$block instanceof $liquid) {
                    $this->blocksQueries->addBlockLogByEntity($player, $block, $liquid, Action::PLACE(), $block->asPosition());
                }
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
     * @ignoreCancelled
     */
    public function trackPlayerInteraction(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();

        if ($this->config->isEnabledWorld(Utils::getLevelNonNull($player->getLevel()))) {
            $itemInHand = $event->getItem();
            $face = $event->getFace();
            $clickedBlock = $event->getBlock();
            $replacedBlock = $clickedBlock->getSide($face);

            if ($event->getAction() === PlayerInteractEvent::LEFT_CLICK_BLOCK) {
                if ($this->config->getBlockBreak() && $replacedBlock instanceof Fire) {
                    $this->blocksQueries->addBlockLogByEntity($player, $replacedBlock, $this->air, Action::BREAK(), $replacedBlock->asPosition());
                } elseif ($this->config->getPlayerInteractions() && $clickedBlock instanceof ItemFrame) {
                    $tile = BlockUtils::asTile($clickedBlock);
                    if ($tile instanceof TileItemFrame && $tile->hasItem()) {
                        //I consider the ItemFrame as a fake inventory holder to only log "removing" item.
                        $this->blocksQueries->addItemFrameLogByPlayer($player, $clickedBlock, $tile->getItem(), Action::REMOVE());
                    }
                }
            } elseif ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
                if ($this->config->getBlockPlace() && $itemInHand instanceof FlintSteel && $replacedBlock instanceof Air) {
                    if ($clickedBlock instanceof TNT) {
                        $this->blocksQueries->addBlockLogByEntity($player, $clickedBlock, $this->air, Action::BREAK(), $clickedBlock->asPosition());
                    } else {
                        $this->blocksQueries->addBlockLogByEntity($player, $this->air, new Fire(), Action::PLACE(), $replacedBlock->asPosition());
                    }
                } elseif ($this->config->getPlayerInteractions()) {
                    if ($itemInHand instanceof Hoe) {
                        if ($clickedBlock instanceof Grass || $clickedBlock instanceof Dirt) {
                            $this->blocksQueries->addBlockLogByEntity($player, $clickedBlock, new Farmland(), Action::PLACE(), $clickedBlock->asPosition());
                            return;
                        }
                    } elseif ($itemInHand instanceof Shovel) {
                        if ($clickedBlock instanceof Grass) {
                            $this->blocksQueries->addBlockLogByEntity($player, $clickedBlock, new GrassPath(), Action::PLACE(), $clickedBlock->asPosition());
                            return;
                        }
                    }

                    if (BlockUtils::canBeClicked($clickedBlock)) {
                        if ($clickedBlock instanceof ItemFrame) {
                            $tile = BlockUtils::asTile($clickedBlock);
                            if ($tile instanceof TileItemFrame && !$tile->hasItem() && !$itemInHand->isNull()) {
                                //I consider the ItemFrame as a fake inventory holder to only log "adding" item.
                                $this->blocksQueries->addItemFrameLogByPlayer($player, $clickedBlock, $itemInHand->setCount(1), Action::ADD());
                            }
                        } else {
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
     * @ignoreCancelled
     */
    public function trackInventoryTransaction(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();

        if ($this->config->isEnabledWorld(Utils::getLevelNonNull($player->getLevel())) && $this->config->getItemTransactions()) {
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
