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

namespace matcracker\BedcoreProtect\storage\queries;

use matcracker\BedcoreProtect\utils\Action;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\entity\Entity;
use pocketmine\Player;

/**
 * It contains all the queries methods related to entities.
 *
 * Trait QueriesEntitiesTrait
 * @package matcracker\BedcoreProtect\storage
 */
trait QueriesEntitiesTrait{

	/**
	 * @param Entity $damager
	 * @param Entity $entity
	 * @param Action $action
	 */
	public function addLogEntityByEntity(Entity $damager, Entity $entity, Action $action) : void{
		$this->addEntity($damager);
		$this->addEntity($entity);

		$this->addRawLog(Utils::getEntityUniqueId($damager), $entity, $action);
		$this->connector->executeInsert(QueriesConst::ADD_ENTITY_LOG, [
			"uuid" => Utils::getEntityUniqueId($entity)
		]);
	}

	public function addEntity(Entity $entity) : void{
		$this->addRawEntity(Utils::getEntityUniqueId($entity), Utils::getEntityName($entity), ($entity instanceof Player) ? $entity->getNetworkSession()->getIp() : "127.0.0.1");
	}

	private function addRawEntity(string $uuid, string $name, string $address = "127.0.0.1") : void{
		$this->connector->executeInsert(QueriesConst::ADD_ENTITY, [
			"uuid" => $uuid,
			"name" => $name,
			"address" => $address
		]);
	}

	//TODO: Rollback and restore entities
}