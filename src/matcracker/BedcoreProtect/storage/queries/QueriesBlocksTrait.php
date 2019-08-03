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

use ArrayOutOfBoundsException;
use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\tasks\AsyncRestoreTask;
use matcracker\BedcoreProtect\tasks\AsyncRollbackTask;
use matcracker\BedcoreProtect\utils\Action;
use matcracker\BedcoreProtect\utils\Area;
use matcracker\BedcoreProtect\utils\BlockUtils;
use matcracker\BedcoreProtect\utils\PrimitiveBlock;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\ItemFrame;
use pocketmine\block\Leaves;
use pocketmine\entity\Entity;
use pocketmine\level\Position;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\tile\Tile;

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
            "old_block_id" => $oldBlock->getId(),
            "old_block_meta" => $oldBlock->getDamage(),
            "old_block_nbt" => $oldTag !== null ? Utils::serializeNBT($oldTag) : null,
            "new_block_id" => $newBlock->getId(),
            "new_block_meta" => $newBlock->getDamage(),
            "new_block_nbt" => $newTag !== null ? Utils::serializeNBT($newTag) : null
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
            $name = "leaves";
        }

        $this->addRawBlockLog("{$name}-uuid", $oldBlock, BlockUtils::getCompoundTag($oldBlock), $newBlock, BlockUtils::getCompoundTag($newBlock), $action, $position);
    }

    /**
     * @param Player $player
     * @param ItemFrame $itemFrame
     * @param CompoundTag $oldItemFrameNbt
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

        if (is_array($newBlocks)) {
            if (empty($newBlocks)) {
                return;
            }

            (function (Block ...$_) { //Type-safe check
            })(... $newBlocks);
        }

        $this->addEntity($entity);

        $rawLogsQuery = $this->buildMultipleRawLogsQuery(Utils::getEntityUniqueId($entity), $oldBlocks, $action);
        $rawBlockLogsQuery = $this->buildMultipleRawBlockLogsQuery($oldBlocks, $newBlocks);

        $this->connector->executeInsertRaw($rawLogsQuery);
        $this->connector->executeInsertRaw($rawBlockLogsQuery);
    }

    /**
     * @param Block[] $oldBlocks
     * @param Block[]|Block $newBlocks
     *
     * @return string
     */
    private function buildMultipleRawBlockLogsQuery(array $oldBlocks, $newBlocks): string
    {
        $query = /**@lang text */
            "INSERT INTO blocks_log(history_id, old_block_id, old_block_meta, old_block_nbt, new_block_id, new_block_meta, new_block_nbt) VALUES";

        $logId = $this->getLastLogId();

        if ($newBlocks instanceof Block) {
            $newId = $newBlocks->getId();
            $newMeta = $newBlocks->getDamage();
            $newNBT = BlockUtils::serializeBlockTileNBT($newBlocks);

            /**@var Block $oldBlock */
            foreach ($oldBlocks as $oldBlock) {
                $logId++;
                $oldId = $oldBlock->getId();
                $oldMeta = $oldBlock->getDamage();
                $oldNBT = BlockUtils::serializeBlockTileNBT($oldBlock);
                $query .= "('{$logId}', '{$oldId}', '{$oldMeta}', '{$oldNBT}', '{$newId}', '{$newMeta}', '{$newNBT}'),";
            }
        } else {
            if (count($oldBlocks) !== count($newBlocks)) {
                throw new ArrayOutOfBoundsException("The number of old blocks must be the same as new blocks, or vice-versa");
            }

            foreach ($oldBlocks as $key => $oldBlock) {
                $logId++;
                $newBlock = $newBlocks[$key];
                $oldId = $oldBlock->getId();
                $oldMeta = $oldBlock->getDamage();
                $oldNBT = BlockUtils::serializeBlockTileNBT($oldBlock);
                $newId = $newBlock->getId();
                $newMeta = $newBlock->getDamage();
                $newNBT = BlockUtils::serializeBlockTileNBT($newBlock);

                $query .= "('{$logId}', '{$oldId}', '{$oldMeta}', '{$oldNBT}', '{$newId}', '{$newMeta}', '{$newNBT}'),";
            }
        }
        $query = mb_substr($query, 0, -1) . ";";

        return $query;
    }

    /**
     * @param bool $rollback
     * @param Area $area
     * @param CommandParser $commandParser
     */
    private function startRollback(bool $rollback, Area $area, CommandParser $commandParser): void
    {
        $startTime = microtime(true);
        $query = $commandParser->buildBlocksLogSelectionQuery($area->getBoundingBox(), !$rollback);
        $this->connector->executeSelectRaw($query, [],
            function (array $rows) use ($rollback, &$blocks, $area, $commandParser, $startTime) {
                $blocks = [];
                if (count($rows) > 0) {
                    $prefix = $rollback ? "old" : "new";
                    foreach ($rows as $row) {
                        $blocks[] = $block = new PrimitiveBlock((int)$row["{$prefix}_block_id"], (int)$row["{$prefix}_block_meta"], (int)$row["x"], (int)$row["y"], (int)$row["z"]);

                        $serializedNBT = $row["{$prefix}_block_nbt"];
                        if (!empty($serializedNBT)) {
                            $nbt = Utils::deserializeNBT($serializedNBT);
                            $tile = Tile::createTile(BlockUtils::getTileName($block->getId()), $area->getWorld(), $nbt);
                            if ($tile !== null) {
                                $area->getWorld()->addTile($tile);
                            }
                        }
                    }
                }
                $task = $rollback ? new AsyncRollbackTask($area, $blocks, $commandParser, $startTime) : new AsyncRestoreTask($area, $blocks, $commandParser, $startTime);
                Server::getInstance()->getAsyncPool()->submitTask($task);
            }
        );
    }
}