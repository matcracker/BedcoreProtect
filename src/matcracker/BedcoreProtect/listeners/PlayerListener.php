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
use matcracker\BedcoreProtect\storage\queries\QueriesConst;
use matcracker\BedcoreProtect\utils\BlockUtils;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerBucketEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\ContainerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\ItemIds;
use pocketmine\math\Facing;
use pocketmine\world\Position;

final class PlayerListener extends BedcoreListener{
	/**
	 * @param PlayerJoinEvent $event
	 *
	 * @priority LOWEST
	 */
	public function onPlayerJoin(PlayerJoinEvent $event) : void{
		$this->database->getQueries()->addEntity($event->getPlayer());
	}

	/**
	 * @param PlayerQuitEvent $event
	 *
	 * @priority LOWEST
	 */
	public function onPlayerQuit(PlayerQuitEvent $event) : void{
		Inspector::removeInspector($event->getPlayer());
	}

	/**
	 * @param PlayerBucketEvent $event
	 *
	 * @priority MONITOR
	 */
	public function trackPlayerBucket(PlayerBucketEvent $event) : void{
		if($this->plugin->getParsedConfig()->getBuckets()){
			$player = $event->getPlayer();
			$block = $event->getBlockClicked();
			$fireEmpty = ($event instanceof PlayerBucketEmptyEvent);

			$bucketDamage = $fireEmpty ? $event->getBucket()->getMeta() : $event->getItem()->getMeta();

			$liquidId = BlockLegacyIds::FLOWING_WATER;
			if($bucketDamage === 10){
				$liquidId = BlockLegacyIds::FLOWING_LAVA;
			}

			$liquid = BlockFactory::get($liquidId, 0, $block->asPosition());

			if($fireEmpty){
				$this->database->getQueries()->addBlockLogByEntity($player, $block, $liquid, QueriesConst::PLACED);
			}else{
				$liquidPos = null;
				$face = $event->getBlockFace();
				if($face === Facing::DOWN){
					$liquidPos = Position::fromObject($liquid->add(0, 1, 0), $liquid->getWorld());
				}else if($face === Facing::UP){
					$liquidPos = Position::fromObject($liquid->subtract(0, 1, 0), $liquid->getWorld());
				}

				$this->database->getQueries()->addBlockLogByEntity($player, $liquid, $block, QueriesConst::BROKE, $liquidPos);
			}
		}
	}

	/**
	 * @param PlayerInteractEvent $event
	 *
	 * @priority MONITOR
	 */
	public function trackPlayerInteraction(PlayerInteractEvent $event) : void{
		$player = $event->getPlayer();
		$clickedBlock = $event->getBlock();
		$item = $event->getItem();
		$action = $event->getAction();
		$face = $event->getFace();

		if($action === PlayerInteractEvent::LEFT_CLICK_BLOCK){
			if(!$event->isCancelled()){
				$relativeBlock = $clickedBlock->getSide($face);
				if($this->plugin->getParsedConfig()->getBlockBreak() && $relativeBlock->getId() === BlockLegacyIds::FIRE){
					$this->database->getQueries()->addBlockLogByEntity($player, $relativeBlock, BlockUtils::createAir($relativeBlock), QueriesConst::BROKE);

				}
			}
		}else if($action === PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			if(Inspector::isInspector($player)){
				if(BlockUtils::hasInventory($clickedBlock)){
					$this->database->getQueries()->requestTransactionLog($player, $clickedBlock);
				}else{
					$this->database->getQueries()->requestBlockLog($player, $clickedBlock);
				}
				$event->setCancelled();

				return;
			}

			if(!$event->isCancelled()){
				if($this->plugin->getParsedConfig()->getBlockPlace() && $item->getId() === ItemIds::FLINT_AND_STEEL){
					$fire = BlockFactory::get(BlockLegacyIds::FIRE, 0, $clickedBlock->getSide($face)->asPosition());
					$this->database->getQueries()->addBlockLogByEntity($player, BlockUtils::createAir($fire->asPosition()), $fire, QueriesConst::PLACED);
				}else if($this->plugin->getParsedConfig()->getPlayerInteractions() && BlockUtils::isActivable($clickedBlock)){
					$this->database->getQueries()->addBlockLogByEntity($player, $clickedBlock, $clickedBlock, QueriesConst::CLICKED);
				}
			}
		}
	}

	/**
	 * @param InventoryTransactionEvent $event
	 *
	 * @priority MONITOR
	 */
	public function trackInventoryTransaction(InventoryTransactionEvent $event) : void{
		if($this->plugin->getParsedConfig()->getItemTransactions()){
			$transaction = $event->getTransaction();
			$player = $transaction->getSource();
			$actions = $transaction->getActions();

			foreach($actions as $action){
				if($action instanceof SlotChangeAction && $action->getInventory() instanceof ContainerInventory){
					$this->database->getQueries()->addLogInventoryByPlayer($player, $action);
					break;
				}
			}
		}
	}
}