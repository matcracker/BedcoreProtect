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

use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\utils\Action;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\math\Vector3;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;
use pocketmine\world\Position;
use poggit\libasynql\SqlError;

/**
 * It contains all the queries methods related to entities.
 *
 * Trait QueriesEntitiesTrait
 * @package matcracker\BedcoreProtect\storage\queries
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

		$nbt = new BigEndianNbtSerializer();
		$tag = $entity->saveNBT()->setFloat("Health", $entity->getMaxHealth());

		$this->connector->executeInsert(QueriesConst::ADD_ENTITY_LOG, [
			"uuid" => Utils::getEntityUniqueId($entity),
			"nbt" => $nbt->writeCompressed(new TreeRoot($tag))
		]);
	}

	public function addEntity(Entity $entity) : void{
		$this->addRawEntity(Utils::getEntityUniqueId($entity), Utils::getEntityName($entity), get_class($entity), ($entity instanceof Player) ? $entity->getNetworkSession()->getIp() : "127.0.0.1");
	}

	private function addRawEntity(string $uuid, string $name, string $classPath = "", string $address = "127.0.0.1") : void{
		$this->connector->executeInsert(QueriesConst::ADD_ENTITY, [
			"uuid" => $uuid,
			"name" => $name,
			"path" => $classPath,
			"address" => $address
		]);
	}

	protected function rollbackEntities(Position $position, CommandParser $parser) : int{
		return $this->executeEntitiesEdit(true, $position, $parser);
	}

	private function executeEntitiesEdit(bool $rollback, Position $position, CommandParser $parser) : int{
		$query = $parser->buildEntitiesLogSelectionQuery($position, !$rollback);
		$totalRows = 0;
		$world = $position->getWorld();
		$this->connector->executeSelectRaw($query, [],
			function(array $rows) use ($rollback, $world, &$totalRows){
				if(count($rows) > 0){
					$query = /**@lang text */
						"UPDATE log_history SET rollback = '{$rollback}' WHERE ";

					foreach($rows as $row){
						$logId = (int) $row["log_id"];
						$entityClass = (string) $row["entity_classpath"];
						$pos = new Vector3((int) $row["x"], (int) $row["y"], (int) $row["z"]);

						try{
							$nbt = (new BigEndianNbtSerializer())->readCompressed($row["entityfrom_nbt"])->getTag();
						}catch(NbtDataException $e){ //zlib decode error / corrupt data
							$nbt = EntityFactory::createBaseNBT($pos);
						}

						$entity = EntityFactory::create($entityClass, $world, $nbt);
						$entity->getId();
						$entity->spawnToAll();

						$query .= "log_id = '$logId' OR ";
					}

					$query = rtrim($query, " OR ") . ";";
					$this->connector->executeInsertRaw($query);
				}

				$totalRows = count($rows);
			},
			function(SqlError $error){
				throw $error;
			}
		);
		$this->connector->waitAll();

		return $totalRows;
	}

	protected function restoreEntities(Position $position, CommandParser $parser) : int{
		//TODO: Currently not working, need to find a way to kill rollback-ed entities.
		return $this->executeEntitiesEdit(false, $position, $parser);
	}
}