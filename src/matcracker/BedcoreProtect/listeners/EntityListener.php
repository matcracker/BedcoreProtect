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

use matcracker\BedcoreProtect\utils\Action;
use matcracker\BedcoreProtect\utils\BlockUtils;
use pocketmine\entity\object\Painting;
use pocketmine\event\entity\EntityBlockChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\player\Player;

final class EntityListener extends BedcoreListener{
	/**
	 * @param EntityExplodeEvent $event
	 *
	 * @priority MONITOR
	 */
	public function trackEntityExplode(EntityExplodeEvent $event) : void{
		$entity = $event->getEntity();
		if($this->configParser->isEnabledWorld($entity->getWorld()) && $this->configParser->getExplosions()){
			$blocks = $event->getBlockList();

			$air = BlockUtils::getAir();
			$this->database->getQueries()->addBlocksLogByEntity($entity, $blocks, $air, Action::BREAK());
		}
	}

	/**
	 * @param EntitySpawnEvent $event
	 *
	 * @priority MONITOR
	 */
	public function trackEntitySpawn(EntitySpawnEvent $event) : void{
		$entity = $event->getEntity();
		if($entity instanceof Painting){
			$player = $entity->getWorld()->getNearestEntity($entity, 5, Player::class);
			if($player !== null){
				$this->database->getQueries()->addLogEntityByEntity($player, $entity, Action::SPAWN());
			}
		}
	}

	/**
	 * @param EntityDespawnEvent $event
	 *
	 * @priority MONITOR
	 */
	public function trackEntityDespawn(EntityDespawnEvent $event) : void{
		$entity = $event->getEntity();
		if($entity instanceof Painting){
			$player = $entity->getWorld()->getNearestEntity($entity, 5, Player::class);
			if($player !== null){
				$this->database->getQueries()->addLogEntityByEntity($player, $entity, Action::DESPAWN());
			}
		}
	}

	/**
	 * @param EntityDeathEvent $event
	 *
	 * @priority MONITOR
	 */
	public function trackEntityDeath(EntityDeathEvent $event) : void{
		$entity = $event->getEntity();
		if($this->configParser->isEnabledWorld($entity->getWorld()) && $this->configParser->getEntityKills()){
			$ev = $entity->getLastDamageCause();
			if($ev instanceof EntityDamageByEntityEvent){
				$damager = $ev->getDamager();
				$this->database->getQueries()->addLogEntityByEntity($damager, $entity, Action::KILL());
			}
		}
	}

	/**
	 * @param EntityBlockChangeEvent $event
	 *
	 * @priority MONITOR
	 */
	public function trackEntityBlockChange(EntityBlockChangeEvent $event) : void{
		$this->database->getQueries()->addBlockLogByEntity($event->getEntity(), $event->getBlock(), $event->getTo(), Action::BREAK(), $event->getBlock());
		$this->database->getQueries()->addBlockLogByEntity($event->getEntity(), $event->getBlock(), $event->getTo(), Action::PLACE());
	}
}