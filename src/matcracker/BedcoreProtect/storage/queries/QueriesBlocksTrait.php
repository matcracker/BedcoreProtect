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
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\math\Area;
use matcracker\BedcoreProtect\serializable\SerializableBlock;
use matcracker\BedcoreProtect\tasks\async\AsyncBlocksQueryGenerator;
use matcracker\BedcoreProtect\tasks\async\AsyncLogsQueryGenerator;
use matcracker\BedcoreProtect\tasks\async\AsyncRestoreTask;
use matcracker\BedcoreProtect\tasks\async\AsyncRollbackTask;
use matcracker\BedcoreProtect\utils\BlockUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\ItemFrame;
use pocketmine\block\Leaves;
use pocketmine\entity\Entity;
use pocketmine\inventory\InventoryHolder;
use pocketmine\item\ItemFactory;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\tile\Chest;
use pocketmine\tile\Tile;
use SOFe\AwaitGenerator\Await;

/**
 * It contains all the queries methods related to blocks.
 *
 * Trait QueriesBlocksTrait
 * @package matcracker\BedcoreProtect\storage\queries
 */
trait QueriesBlocksTrait
{

    /**
     * It logs the entity who makes the action for block.
     *
     * @param Entity $entity
     * @param Block $oldBlock
     * @param Block $newBlock
     * @param Action $action
     * @param Position|null $position
     */
    public function addBlockLogByEntity(Entity $entity, Block $oldBlock, Block $newBlock, Action $action, ?Position $position = null): void
    {
        $this->addEntity($entity);
        $this->addRawBlockLog(Utils::getEntityUniqueId($entity), $oldBlock, BlockUtils::getCompoundTag($oldBlock), $newBlock, BlockUtils::getCompoundTag($newBlock), $action, $position);
    }

    protected final function addRawBlockLog(string $uuid, Block $oldBlock, ?CompoundTag $oldTag, Block $newBlock, ?CompoundTag $newTag, Action $action, ?Position $position = null): void
    {
        $pos = $position ?? $newBlock->asPosition();
        $this->addRawLog($uuid, $pos, $action);
        $this->connector->executeInsert(QueriesConst::ADD_BLOCK_LOG, [
            'old_id' => $oldBlock->getId(),
            'old_meta' => $oldBlock->getDamage(),
            'old_nbt' => $oldTag !== null ? Utils::serializeNBT($oldTag) : null,
            'new_id' => $newBlock->getId(),
            'new_meta' => $newBlock->getDamage(),
            'new_nbt' => $newTag !== null ? Utils::serializeNBT($newTag) : null
        ]);
    }

    /**
     * It logs the block who mades the action for block.
     *
     * @param Block $who
     * @param Block $oldBlock
     * @param Block $newBlock
     * @param Action $action
     * @param Position|null $position
     */
    public function addBlockLogByBlock(Block $who, Block $oldBlock, Block $newBlock, Action $action, ?Position $position = null): void
    {
        $name = $who->getName();
        //Particular blocks
        if ($who instanceof Leaves) {
            $name = 'leaves';
        }

        $this->addRawBlockLog("{$name}-uuid", $oldBlock, BlockUtils::getCompoundTag($oldBlock), $newBlock, BlockUtils::getCompoundTag($newBlock), $action, $position);
    }

    /**
     * @param Player $player
     * @param ItemFrame $itemFrame
     * @param CompoundTag|null $oldItemFrameNbt
     * @param Action $action
     */
    public function addItemFrameLogByPlayer(Player $player, ItemFrame $itemFrame, ?CompoundTag $oldItemFrameNbt, Action $action): void
    {
        $this->addRawBlockLog(Utils::getEntityUniqueId($player), $itemFrame, $oldItemFrameNbt, $itemFrame, BlockUtils::getCompoundTag($itemFrame), $action);
    }

    /**
     * @param Entity $entity
     * @param Block[] $oldBlocks
     * @param Block[]|Block $newBlocks
     * @param Action $action
     */
    public function addBlocksLogByEntity(Entity $entity, array $oldBlocks, $newBlocks, Action $action): void
    {
        if (empty($oldBlocks)) {
            return;
        }

        $oldBlocks = array_map(static function (Block $block): SerializableBlock {
            return SerializableBlock::toSerializableBlock($block);
        }, $oldBlocks);

        if (is_array($newBlocks)) {
            if (empty($newBlocks)) {
                return;
            }

            (function (Block ...$_) { //Type-safe check
            })(... $newBlocks);
            $newBlocks = array_map(static function (Block $block): SerializableBlock {
                return SerializableBlock::toSerializableBlock($block);
            }, $newBlocks);
        } else {
            $newBlocks = SerializableBlock::toSerializableBlock($newBlocks);
        }

        $this->addEntity($entity);

        Await::f2c(function () use ($entity, $oldBlocks, $newBlocks, $action) {
            $lastLogId = (int)(yield $this->getLastLogId())[0]['lastId'];
            $blocksTask = new AsyncBlocksQueryGenerator($this->connector, $lastLogId, $oldBlocks, $newBlocks);
            $logsTask = new AsyncLogsQueryGenerator($this->connector, Utils::getEntityUniqueId($entity), $oldBlocks, $action, $blocksTask);
            Server::getInstance()->getAsyncPool()->submitTask($logsTask);
        }, function () {
            //NOOP
        });

    }

    /**
     * @param bool $rollback
     * @param Area $area
     * @param CommandParser $commandParser
     */
    private function startRollback(bool $rollback, Area $area, CommandParser $commandParser): void
    {
        Await::f2c(function () use ($rollback, $area, $commandParser) {
            $startTime = microtime(true);
            $prefix = $rollback ? 'old' : 'new';
            $world = $area->getWorld();

            $query = $commandParser->buildLogsSelectionQuery(!$rollback, $area->getBoundingBox());
            $logRows = yield $this->connector->executeSelectRaw($query, [], yield, yield Await::REJECT) => Await::ONCE;

            /**@var int[] $logIds */
            $logIds = [];
            foreach ($logRows as $logRow) {
                $logIds[] = (int)$logRow['log_id'];
            }

            $blockRows = yield $this->connector->executeSelect($rollback ? QueriesConst::GET_ROLLBACK_OLD_BLOCKS : QueriesConst::GET_ROLLBACK_NEW_BLOCKS, ['log_ids' => $logIds], yield, yield Await::REJECT) => Await::ONCE;

            $inclusions = $commandParser->getBlocks();
            $exclusions = $commandParser->getExclusions();
            /**@var SerializableBlock[] $blocks */
            $blocks = [];
            foreach ($blockRows as $index => $row) {
                $historyId = (int)$row['history_id'];
                $id = (int)$row["{$prefix}_id"];
                $meta = (int)$row["{$prefix}_meta"];
                if (!empty($inclusions)) {
                    foreach ($inclusions as $inclusion) {
                        if ($inclusion->getId() !== $id && $inclusion->getDamage() !== $meta) {
                            unset($blockRows[$index]);
                            unset($logIds[array_search($historyId, $logIds)]);
                        }
                    }
                }
                if (!empty($exclusions)) {
                    foreach ($exclusions as $exclusion) {
                        if ($exclusion->getId() === $id && $exclusion->getDamage() === $meta) {
                            unset($blockRows[$index]);
                            unset($logIds[array_search($historyId, $logIds)]);
                        }
                    }
                }
            }

            foreach ($blockRows as $row) {
                $serializedNBT = (string)$row["{$prefix}_nbt"];
                $blocks[] = $block = new SerializableBlock((int)$row["{$prefix}_id"], (int)$row["{$prefix}_meta"], (int)$row['x'], (int)$row['y'], (int)$row['z'], (string)$row['world_name'], $serializedNBT);
                if (!empty($serializedNBT)) {
                    $nbt = Utils::deserializeNBT($serializedNBT);
                    $tile = Tile::createTile(BlockUtils::getTileName($block), $world, $nbt);
                    if ($tile !== null) {
                        if ($tile instanceof InventoryHolder && !$this->configParser->getRollbackItems()) {
                            $tile->getInventory()->clearAll();
                        }
                        $world->addTile($tile);
                    }
                }
            }
            $touchedChunks = $area->getTouchedChunks($blocks);

            $inventoryRows = [];
            if ($this->configParser->getRollbackItems()) {
                $inventoryRows = yield $this->connector->executeSelect($rollback ? QueriesConst::GET_ROLLBACK_OLD_INVENTORIES : QueriesConst::GET_ROLLBACK_NEW_INVENTORIES, ['log_ids' => $logIds], yield, yield Await::REJECT) => Await::ONCE;
                foreach ($inventoryRows as $row) {
                    $amount = (int)$row["{$prefix}_amount"];
                    $nbt = Utils::deserializeNBT($row["{$prefix}_nbt"]);
                    $item = ItemFactory::get((int)$row["{$prefix}_id"], (int)$row["{$prefix}_meta"], $amount, $nbt);
                    $vector = new Vector3((int)$row['x'], (int)$row['y'], (int)$row['z']);
                    $tile = $world->getTile($vector);
                    if ($tile instanceof InventoryHolder) {
                        $slot = (int)$row['slot'];
                        $inv = ($tile instanceof Chest) ? $tile->getRealInventory() : $tile->getInventory();
                        $inv->setItem($slot, $item);
                    }
                }
            }

            $entityRows = [];
            if ($this->configParser->getRollbackEntities()) {
                $entityRows = yield $this->connector->executeSelect(QueriesConst::GET_ROLLBACK_ENTITIES, ['log_ids' => $logIds], yield, yield Await::REJECT) => Await::ONCE;
            }
            $task = $rollback ? new AsyncRollbackTask($area, $blocks, $commandParser, $startTime, $logIds) : new AsyncRestoreTask($area, $blocks, $commandParser, $startTime, $logIds);
            Server::getInstance()->getAsyncPool()->submitTask($task);

            $this->onRollbackComplete($rollback, $area, $commandParser, $startTime, count($touchedChunks), count($blocks), count($inventoryRows), count($entityRows));
        }, function () {
            //NOOP
        });
    }

    private function onRollbackComplete(bool $rollback, Area $area, CommandParser $commandParser, float $startTime, int $chunks, int $blocks, int $items, int $entities): void
    {
        $duration = round(microtime(true) - $startTime, 2);
        if (($sender = Server::getInstance()->getPlayer($commandParser->getSenderName())) !== null) {
            $date = Utils::timeAgo(time() - $commandParser->getTime());
            $lang = Main::getInstance()->getLanguage();

            $sender->sendMessage(Utils::translateColors('&f------'));
            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString(($rollback ? 'rollback' : 'restore') . '.completed', [$area->getWorld()->getName()])));
            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString(($rollback ? 'rollback' : 'restore') . '.date', [$date])));
            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString('rollback.radius', [$commandParser->getRadius()])));
            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString('rollback.blocks', [$blocks])));
            if ($items > 0) {
                $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString('rollback.items', [$items])));
            }
            if ($entities > 0) {
                $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString('rollback.entities', [$entities])));
            }
            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString('rollback.modified-chunks', [$chunks])));
            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString('rollback.time-taken', [$duration])));
            $sender->sendMessage(Utils::translateColors('&f------'));
        }
    }
}