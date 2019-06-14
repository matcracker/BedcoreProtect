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
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\QueriesConst;
use matcracker\BedcoreProtect\utils\BlockUtils;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIds;
use pocketmine\block\Liquid;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\block\BlockFormEvent;
use pocketmine\event\block\BlockGrowEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerBucketEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\ContainerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\tile\Sign;

final class TrackerListener implements Listener
{
    private $plugin;
    private $database;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        $this->database = $plugin->getDatabase();
    }

    /**
     * @param BlockBreakEvent $event
     * @priority MONITOR
     */
    public function trackBlockBreak(BlockBreakEvent $event): void
    {
        if ($this->plugin->getParsedConfig()->getBlockBreak()) {
            $player = $event->getPlayer();
            $block = $event->getBlock();

            if (Inspector::isInspector($player)) { //It checks the block clicked //TODO: Move out of there this check in a properly listener
                $this->database->getQueries()->requestBlockLog($player, $block);
                $event->setCancelled();
            } else {
                $tile = $block->getLevel()->getTile($block);
                if ($tile instanceof Sign) {
                    $this->database->getQueries()->addSignLogByPlayer($player, $tile);
                } else {
                    $air = BlockUtils::createAir($block->asPosition());
                    $this->database->getQueries()->addBlockLogByEntity($player, $block, $air, QueriesConst::BROKE);
                }
            }
        }
    }

    /**
     * @param BlockPlaceEvent $event
     * @priority MONITOR
     */
    public function trackBlockPlace(BlockPlaceEvent $event): void
    {
        if ($this->plugin->getParsedConfig()->getBlockPlace()) {
            $player = $event->getPlayer();
            $block = $event->getBlock();
            $replacedBlock = $event->getBlockReplaced();

            if (Inspector::isInspector($player)) { //It checks the block where the player places. //TODO: Move out of there this check in a properly listener
                $this->database->getQueries()->requestBlockLog($player, $replacedBlock);
                $event->setCancelled();
            } else {
                /*if ($block instanceof Bed) {
                    $half = $block->getOtherHalf();
                    var_dump($half);
                    if ($half !== null) {
                        $this->database->getQueries()->logPlayer($player, $replacedBlock, $half, Queries::PLACED);
                    }
                } else if ($block instanceof Door) {
                    $upperDoor = BlockFactory::get($block->getId(), $block->getDamage() | 0x01, $block->asPosition())
                    $this->database->getQueries()->logPlayer($player, $replacedBlock, $upperDoor, Queries::PLACED);

                }*/
                $this->database->getQueries()->addBlockLogByEntity($player, $replacedBlock, $block, QueriesConst::PLACED);
            }
        }
    }

    /**
     * @param BlockSpreadEvent $event
     * @priority MONITOR
     */
    public function trackBlockSpread(BlockSpreadEvent $event): void
    {
        $block = $event->getBlock();
        $source = $event->getSource();

        if ($source instanceof Liquid) {
            if (BlockUtils::isStillLiquid($source)) {
                /*print_r("SOURCE(" . $source->getName() . ")\n" . $source->asPosition());
                print_r("\nBLOCK(" . $block->getName() . ")\n" . $block->asPosition());
                print_r("\nNEW STATE(" . $newState->getName() . ")\n" . $newState->asPosition() . "\n\n");*/

                $this->database->getQueries()->addBlockLogByBlock($block, $source, QueriesConst::PLACED);
            } /*else {
                //TODO: Find player who place water
            }*/
        }
    }

    /**
     * @param BlockBurnEvent $event
     * @priority MONITOR
     */
    public function trackBlockBurn(BlockBurnEvent $event): void
    {
        $block = $event->getBlock();
        $cause = $event->getCausingBlock();

        $this->database->getQueries()->addBlockLogByBlock($block, $cause, QueriesConst::BROKE);
    }

    /**
     * @param PlayerBucketEvent $event
     * @priority MONITOR
     */
    public function trackPlayerBucket(PlayerBucketEvent $event): void
    {
        if ($this->plugin->getParsedConfig()->getBuckets()) {
            $player = $event->getPlayer();
            $block = $event->getBlockClicked();
            $fireEmpty = ($event instanceof PlayerBucketEmptyEvent);

            $bucketDamage = $fireEmpty ? $event->getBucket()->getDamage() : $event->getItem()->getDamage();

            $liquidId = BlockIds::FLOWING_WATER;
            if ($bucketDamage === 10) {
                $liquidId = BlockIds::FLOWING_LAVA;
            }

            $liquid = BlockFactory::get($liquidId, 0, $block->asPosition());

            if ($fireEmpty) {
                $this->database->getQueries()->addBlockLogByEntity($player, $block, $liquid, QueriesConst::PLACED);
            } else {
                $liquidPos = null;
                $face = $event->getBlockFace();
                if ($face === Vector3::SIDE_DOWN) {
                    $liquidPos = Position::fromObject($liquid->add(0, 1, 0), $liquid->getLevel());
                } else if ($face === Vector3::SIDE_UP) {
                    $liquidPos = Position::fromObject($liquid->subtract(0, 1, 0), $liquid->getLevel());
                }

                $this->database->getQueries()->addBlockLogByEntity($player, $liquid, $block, QueriesConst::BROKE, $liquidPos);
            }
        }
    }

    /**
     * @param BlockFormEvent $event
     * @priority MONITOR
     */
    public function trackBlockForm(BlockFormEvent $event): void
    {
        $block = $event->getBlock();
        $result = $event->getNewState();

        if ($block instanceof Liquid) { //TODO: FIX ME
            $this->database->getQueries()->addBlockLogByBlock($block, $result, QueriesConst::PLACED, $block->asPosition());
        }
    }

    /**
     * @param EntityExplodeEvent $event
     * @priority MONITOR
     */
    public function trackEntityExplode(EntityExplodeEvent $event): void
    {
        if ($this->plugin->getParsedConfig()->getExplosions()) {
            $entity = $event->getEntity();
            $blocks = $event->getBlockList();

            if ($entity instanceof PrimedTNT) {
                $air = BlockUtils::createAir();
                $this->database->getQueries()->addBlocksLogByEntity($entity, $blocks, $air, QueriesConst::BROKE);
            }
        }
    }

    public function testGrow(BlockGrowEvent $event): void
    {
        //TODO
    }

    /**
     * @param PlayerInteractEvent $event
     * @priority MONITOR
     */
    public function trackPlayerInteraction(PlayerInteractEvent $event): void
    {

        $player = $event->getPlayer();
        $block = $event->getBlock();
        $item = $event->getItem();
        $action = $event->getAction();
        $face = $event->getFace();

        /*if ($item->getId() === ItemIds::FLINT_AND_STEEL && $action === PlayerInteractEvent::RIGHT_CLICK_BLOCK) { //TODO: wait API 4.0.0
            $firePos = $block->getSide(Vector3::getOppositeSide($face)); //TODO: IF FACE IS SIDE UP POS->ADD Y+1 ELSE SIDE DOWN SUB Y-1
            $air = BlockFactory::get(BlockIds::AIR, 0, $firePos);
            $fire = BlockFactory::get(BlockIds::FIRE, 0, $firePos);
            var_dump($firePos->asVector3());
            $this->database->getQueries()->logPlayer($player, $air, $fire, Queries::PLACED);

        }*/
        if ($this->plugin->getParsedConfig()->getPlayerInteractions()) {
            if (BlockUtils::isActivable($block)) {
                if (Inspector::isInspector($player)) {
                    if (BlockUtils::hasInventory($block)) {
                        $this->database->getQueries()->requestTransactionLog($player, $block);
                    } else {
                        $this->database->getQueries()->requestBlockLog($player, $block);
                    }
                    $event->setCancelled();
                } else {
                    $this->database->getQueries()->addBlockLogByEntity($player, $block, $block, QueriesConst::CLICKED);
                }
            }
        }

    }

    /**
     * @param EntityDeathEvent $event
     * @priority MONITOR
     */
    public function trackEntityDeath(EntityDeathEvent $event): void
    {
        if ($this->plugin->getParsedConfig()->getEntityKills()) {
            $entity = $event->getEntity();
            $ev = $entity->getLastDamageCause();
            if ($ev instanceof EntityDamageByEntityEvent) {
                $damager = $ev->getDamager();
                $this->database->getQueries()->addLogEntityByEntity($damager, $entity);
            }
        }
    }

    /**
     * @param InventoryTransactionEvent $event
     * @priority MONITOR
     */
    public function trackInventoryTransaction(InventoryTransactionEvent $event): void
    {
        if ($this->plugin->getParsedConfig()->getItemTransactions()) {
            $transaction = $event->getTransaction();
            $player = $transaction->getSource();
            $actions = $transaction->getActions();

            foreach ($actions as $action) {
                if ($action instanceof SlotChangeAction && $action->getInventory() instanceof ContainerInventory) {
                    $this->database->getQueries()->addLogInventoryByPlayer($player, $action);
                    break;
                }
            }
        }
    }

    /**
     * @param PlayerJoinEvent $event
     * @priority LOWEST
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        $this->database->getQueries()->addEntity($event->getPlayer());
    }

    /**
     * @param PlayerQuitEvent $event
     * @priority LOWEST
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        Inspector::removeInspector($event->getPlayer());
    }
}