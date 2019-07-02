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
use matcracker\BedcoreProtect\Inspector;
use matcracker\BedcoreProtect\utils\Action;
use matcracker\BedcoreProtect\utils\BlockUtils;
use matcracker\BedcoreProtect\utils\ConfigParser;
use pocketmine\block\Block;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use poggit\libasynql\DataConnector;
use poggit\libasynql\SqlError;

class Queries{
	use QueriesBlocksTrait, QueriesInventoriesTrait, QueriesEntitiesTrait;

	/**
	 * @var DataConnector
	 */
	protected $connector;

	protected $configParser;

	public function __construct(DataConnector $connector, ConfigParser $configParser){
		$this->connector = $connector;
		$this->configParser = $configParser;
	}

	public function init() : void{
		foreach(QueriesConst::INIT_TABLES as $queryTable){
			$this->connector->executeGeneric($queryTable);
		}
		$this->connector->waitAll();
		$this->addDefaultEntities();
		$this->addDefaultBlocks();

		if($this->configParser->isSQLite()){
			$this->beginTransaction();
		}
	}

	private function addDefaultEntities() : void{
		$uuid = Server::getInstance()->getServerUniqueId()->toString();
		$this->addRawEntity($uuid, "#console");
		$this->addRawEntity("flow-uuid", "#flow");
		$this->addRawEntity("water-uuid", "#water");
		$this->addRawEntity("still water-uuid", "#water");
		$this->addRawEntity("lava-uuid", "#lava");
		$this->addRawEntity("fire block-uuid", "#fire");
		$this->addRawEntity("leaves-uuid", "#decay");
	}

	private function addDefaultBlocks() : void{
		$this->addBlock(BlockUtils::getAir());
	}

	/**
	 * Can be used only with SQLite
	 */
	public final function beginTransaction() : void{
		if($this->configParser->isSQLite()){
			$this->connector->executeGeneric(QueriesConst::BEGIN_TRANSACTION);
		}
	}

	public function requestNearLog(Player $inspector, Position $position, int $near) : void{
		$this->requestLog(QueriesConst::GET_NEAR_LOG, $inspector, $position, $near);
	}

	/**
	 * @param string   $queryName
	 * @param Player   $inspector
	 * @param Position $position
	 * @param int|null $near
	 */
	private function requestLog(string $queryName, Player $inspector, Position $position, int $near = 0) : void{
		$minV = $position->subtract($near, $near, $near)->floor();
		$maxV = $position->add($near, $near, $near)->floor();

		$this->connector->executeSelect($queryName, [
			"min_x" => $minV->getX(),
			"max_x" => $maxV->getX(),
			"min_y" => $minV->getY(),
			"max_y" => $maxV->getY(),
			"min_z" => $minV->getZ(),
			"max_z" => $maxV->getZ(),
			"world_name" => $position->getWorld()->getFolderName()
		], function(array $rows) use ($inspector){
			Inspector::cacheLogs($inspector, $rows);
			Inspector::parseLogs($inspector, $rows);
		});
	}

	public function requestLookup(CommandSender $sender, CommandParser $parser) : void{
		$query = $parser->buildLookupQuery();
		$this->connector->executeSelectRaw($query, [], function(array $rows) use ($sender){
			Inspector::cacheLogs($sender, $rows);
			Inspector::parseLogs($sender, $rows);
		});
	}

	public function rollback(Position $position, CommandParser $parser, ?callable $onSuccess = null, ?callable $onError = null) : void{
		try{
			$rows = $this->rollbackBlocks($position, $parser);
			if($this->configParser->getRollbackItems())
				$rows += $this->rollbackItems($position, $parser);
			if($this->configParser->getRollbackEntities())
				$rows += $this->rollbackEntities($position, $parser);

			if($onSuccess !== null){
				$onSuccess($rows);
			}
		}catch(SqlError $error){
			if($onError !== null){
				$onError($error);
			}
		}
	}

	public function restore(Position $position, CommandParser $parser, ?callable $onSuccess = null, ?callable $onError = null) : void{
		try{
			$rows = $this->restoreBlocks($position, $parser);
			if($this->configParser->getRollbackItems())
				$rows += $this->restoreItems($position, $parser);
			if($this->configParser->getRollbackEntities())
				$rows += $this->restoreEntities($position, $parser);

			if($onSuccess !== null){
				$onSuccess($rows);
			}
		}catch(SqlError $error){
			if($onError !== null){
				$onError($error);
			}
		}
	}

	public function requestTransactionLog(Player $inspector, Position $position) : void{
		$this->requestLog(QueriesConst::GET_TRANSACTION_LOG, $inspector, $position);
	}

	public function requestBlockLog(Player $inspector, Block $block) : void{
		$this->requestLog(QueriesConst::GET_BLOCK_LOG, $inspector, $block->asPosition());
	}

	public function purge(int $time, ?callable $onSuccess = null) : void{
		$this->connector->executeChange(QueriesConst::PURGE, [
			"time" => $time
		], $onSuccess);
	}

	/**
	 * Can be used only with SQLite
	 */
	public final function endTransaction() : void{
		if($this->configParser->isSQLite()){
			$this->connector->executeGeneric(QueriesConst::END_TRANSACTION);
		}
	}

	private function addRawLog(string $uuid, Position $position, Action $action) : void{
		$this->connector->executeInsert(QueriesConst::ADD_HISTORY_LOG, [
			"uuid" => strtolower($uuid),
			"x" => (int) $position->getX(),
			"y" => (int) $position->getY(),
			"z" => (int) $position->getZ(),
			"world_name" => $position->getWorld()->getFolderName(),
			"action" => $action->getType()
		]);
	}

	/**
	 * Returns a single query that add multiple raw logs
	 *
	 * @param string     $uuid
	 * @param Position[] $positions
	 * @param Action     $action
	 *
	 * @return string
	 */
	private function buildMultipleRawLogsQuery(string $uuid, array $positions, Action $action) : string{
		$query = /**@lang text */
			"INSERT INTO log_history(who, x, y, z, world_name, action) VALUES";

		foreach($positions as $position){
			$x = (int) $position->getX();
			$y = (int) $position->getY();
			$z = (int) $position->getZ();
			$levelName = $position->getWorld()->getFolderName(); //It picks the first element because the level must be the same.
			$query .= "((SELECT uuid FROM entities WHERE uuid = '{$uuid}'), '{$x}', '{$y}', '{$z}', '{$levelName}', '{$action->getType()}'),";
		}

		$query = rtrim($query, ",") . ";";

		return $query;
	}

	private function getLastLogId() : int{
		$id = 0;
		$this->connector->executeSelect(QueriesConst::GET_LAST_LOG_ID, [],
			function(array $rows) use (&$id){
				if(count($rows) === 1){
					$id = (int) $rows[0]["lastId"];
				}
			}
		);
		$this->connector->waitAll();

		return $id;
	}
}