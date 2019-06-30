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
use matcracker\BedcoreProtect\utils\BlockUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\Leaves;
use pocketmine\block\Sign;
use pocketmine\entity\Entity;
use pocketmine\player\Player;
use pocketmine\world\Position;
use poggit\libasynql\SqlError;

/**
 * It contains all the queries methods related to blocks.
 *
 * Trait QueriesBlocksTrait
 * @package matcracker\BedcoreProtect\storage\queries
 */
trait QueriesBlocksTrait{

	/**
	 * It logs the entity who mades the action for block.
	 *
	 * @param Entity        $entity
	 * @param Block         $oldBlock
	 * @param Block         $newBlock
	 * @param Action        $action
	 * @param Position|null $position
	 */
	public function addBlockLogByEntity(Entity $entity, Block $oldBlock, Block $newBlock, Action $action, ?Position $position = null) : void{
		$this->addEntity($entity);
		$this->addRawBlockLog(Utils::getEntityUniqueId($entity), $oldBlock, $newBlock, $action, $position);
	}

	/**
	 * @param string        $uuid
	 * @param Block         $oldBlock
	 * @param Block         $newBlock
	 * @param Action        $action
	 * @param Position|null $position
	 *
	 * @internal
	 */
	private function addRawBlockLog(string $uuid, Block $oldBlock, Block $newBlock, Action $action, ?Position $position = null) : void{
		$this->addBlock($oldBlock);
		$this->addBlock($newBlock);

		$pos = $position ?? $newBlock->asPosition();
		$this->addRawLog($uuid, $pos, $action);
		$this->connector->executeInsert(QueriesConst::ADD_BLOCK_LOG, [
			"old_id" => $oldBlock->getId(),
			"old_damage" => $oldBlock->getMeta(),
			"new_id" => $newBlock->getId(),
			"new_damage" => $newBlock->getMeta()
		]);
	}

	/**
	 * It registers the block inside 'blocks' table
	 *
	 * @param Block $block
	 */
	public function addBlock(Block $block) : void{
		$this->connector->executeInsert(QueriesConst::ADD_BLOCK, [
			"id" => $block->getId(),
			"damage" => $block->getMeta(),
			"name" => $block->getName()
		]);
	}

	/**
	 * It logs the block who mades the action for block.
	 *
	 * @param Block         $who
	 * @param Block         $oldBlock
	 * @param Block         $newBlock
	 * @param Action        $action
	 * @param Position|null $position
	 */
	public function addBlockLogByBlock(Block $who, Block $oldBlock, Block $newBlock, Action $action, ?Position $position = null) : void{
		$name = $who->getName();
		//Particular blocks
		if($who->getId() instanceof Leaves){
			$name = "leaves";
		}

		$this->addRawBlockLog("{$name}-uuid", $oldBlock, $newBlock, $action, $position);
	}

	/**
	 * It logs the text and position of the removed sign.
	 *
	 * @param Player $player
	 * @param Sign   $sign
	 */
	public function addSignLogByPlayer(Player $player, Sign $sign) : void{
		$air = BlockUtils::getAir($sign->asPosition());

		$this->addRawBlockLog(Utils::getEntityUniqueId($player), $sign, $air, Action::BREAK());
		$this->connector->executeInsert(QueriesConst::ADD_SIGN_LOG, [
			"lines" => json_encode($sign->getText()->getLines())
		]);
	}

	/**
	 * @param Entity        $entity
	 * @param Block[]       $oldBlocks
	 * @param Block[]|Block $newBlocks
	 * @param Action        $action
	 */
	public function addBlocksLogByEntity(Entity $entity, array $oldBlocks, $newBlocks, Action $action) : void{
		$this->addEntity($entity);

		$oldBlocksQuery = $this->buildMultipleBlocksQuery($oldBlocks);
		$this->connector->executeInsertRaw($oldBlocksQuery);

		if(is_array($newBlocks)){
			(function(Block ...$_){
			})(... $newBlocks);
			$newBlocksQuery = $this->buildMultipleBlocksQuery($newBlocks);
			$this->connector->executeInsertRaw($newBlocksQuery);
		}else{
			$this->addBlock($newBlocks);
		}

		$rawLogsQuery = $this->buildMultipleRawLogsQuery(Utils::getEntityUniqueId($entity), $oldBlocks, $action);
		$rawBlockLogsQuery = $this->buildMultipleRawBlockLogsQuery($oldBlocks, $newBlocks);

		$this->connector->executeInsertRaw($rawLogsQuery);
		$this->connector->executeInsertRaw($rawBlockLogsQuery);
	}

	/**
	 * @param Block[] $blocks
	 *
	 * @return string
	 */
	private function buildMultipleBlocksQuery(array $blocks) : string{
		$query = /**@lang text */
			"REPLACE INTO blocks (id, damage, block_name) VALUES";

		$filtered = array_unique(array_map(function(Block $element){
			return $element->getId() . ":" . $element->getMeta() . ":" . $element->getName();
		}, $blocks));

		foreach($filtered as $value){
			$blockData = explode(":", $value);
			$id = (int) $blockData[0];
			$damage = (int) $blockData[1];
			$name = (string) $blockData[2];
			$query .= "('$id', '$damage', '$name'),";
		}
		$query = rtrim($query, ",") . ";";

		return $query;
	}

	/**
	 * @param array         $oldBlocks
	 * @param Block[]|Block $newBlocks
	 *
	 * @return string
	 */
	private function buildMultipleRawBlockLogsQuery(array $oldBlocks, $newBlocks) : string{
		$query = /**@lang text */
			"INSERT INTO blocks_log(history_id, old_block_id, old_block_damage, new_block_id, new_block_damage) VALUES";

		$logId = $this->getLastLogId();

		if(!is_array($newBlocks) && $newBlocks instanceof Block){
			$newId = $newBlocks->getId();
			$newDamage = $newBlocks->getMeta();

			foreach($oldBlocks as $oldBlock){
				$logId++;
				$oldId = $oldBlock->getId();
				$oldDamage = $oldBlock->getDamage();
				$query .= "('$logId', (SELECT id FROM blocks WHERE blocks.id = '$oldId' AND damage = '$oldDamage'),
                (SELECT damage FROM blocks WHERE blocks.id = '$oldId' AND damage = '$oldDamage'),
                (SELECT id FROM blocks WHERE blocks.id = '$newId' AND damage = '$newDamage'),
                (SELECT damage FROM blocks WHERE blocks.id = '$newId' AND damage = '$newDamage')),";
			}
		}else{
			foreach($oldBlocks as $key => $oldBlock){
				$logId++;
				$oldId = $oldBlock->getId();
				$oldDamage = $oldBlock->getDamage();
				$newId = $newBlocks[$key]->getId();
				$newDamage = $newBlocks[$key]->getDamage();

				$query .= "('$logId', (SELECT id FROM blocks WHERE blocks.id = '$oldId' AND damage = '$oldDamage'),
                (SELECT damage FROM blocks WHERE blocks.id = '$oldId' AND damage = '$oldDamage'),
                (SELECT id FROM blocks WHERE blocks.id = '$newId' AND damage = '$newDamage'),
                (SELECT damage FROM blocks WHERE blocks.id = '$newId' AND damage = '$newDamage')),";
			}
		}
		$query = rtrim($query, ",") . ";";

		return $query;
	}

	protected function rollbackBlocks(Position $position, CommandParser $parser) : int{
		return $this->executeBlocksEdit(true, $position, $parser);
	}

	/**
	 * @param bool          $rollback
	 * @param Position      $position
	 * @param CommandParser $parser
	 *
	 * @return int Returns the rows number
	 */
	private function executeBlocksEdit(bool $rollback, Position $position, CommandParser $parser) : int{
		$query = $parser->buildBlocksLogSelectionQuery($position, !$rollback);
		$totalRows = 0;
		$world = $position->getWorld();
		$this->connector->executeSelectRaw($query, [],
			function(array $rows) use ($rollback, $world, &$totalRows){
				if(count($rows) > 0){
					$query = /**@lang text */
						"UPDATE log_history SET rollback = '{$rollback}' WHERE ";

					foreach($rows as $row){
						$logId = (int) $row["log_id"];
						$prefix = $rollback ? "old" : "new";
						$pos = new Position((int) $row["x"], (int) $row["y"], (int) $row["z"], $world);
						$block = BlockFactory::get((int) $row["{$prefix}_block_id"], (int) $row["{$prefix}_block_damage"], $pos);

						if($block instanceof Sign){
							if($this->configParser->getSignText()){
								$this->connector->executeSelect(QueriesConst::GET_SIGN_LOG, ["id" => $logId],
									function(array $rows) use ($block, $world){
										if(count($rows) === 1){
											$texts = (array) json_decode($rows[0]["text_lines"], true);
											$block->getText()->setLines($texts);
											$world->setBlock($block, $block);

										}
									}
								);
							}
						}else{
							$world->setBlock($block, $block);
							$block->onPostPlace();
						}

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

	protected function restoreBlocks(Position $position, CommandParser $parser) : int{
		return $this->executeBlocksEdit(false, $position, $parser);
	}
}