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

use Closure;
use Generator;
use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\math\Area;
use matcracker\BedcoreProtect\serializable\SerializableBlock;
use matcracker\BedcoreProtect\tasks\async\BlocksQueryGeneratorTask;
use matcracker\BedcoreProtect\tasks\async\LogsQueryGeneratorTask;
use matcracker\BedcoreProtect\tasks\async\RollbackTask;
use matcracker\BedcoreProtect\utils\BlockUtils;
use matcracker\BedcoreProtect\utils\ConfigParser;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\ItemFrame;
use pocketmine\block\Leaves;
use pocketmine\entity\Entity;
use pocketmine\inventory\InventoryHolder;
use pocketmine\level\Position;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\tile\Tile;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Await;
use function array_map;
use function array_search;
use function count;
use function is_array;
use function strlen;

/**
 * It contains all the queries methods related to blocks.
 *
 * Class BlocksQueries
 * @package matcracker\BedcoreProtect\storage\queries
 */
class BlocksQueries extends Query
{
    /** @var EntitiesQueries */
    protected $entitiesQueries;

    public function __construct(DataConnector $connector, ConfigParser $configParser, EntitiesQueries $entitiesQueries)
    {
        parent::__construct($connector, $configParser);
        $this->entitiesQueries = $entitiesQueries;
    }

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
        $oldTag = BlockUtils::getCompoundTag($oldBlock);
        $newTag = BlockUtils::getCompoundTag($newBlock);

        Await::f2c(
            function () use ($entity): Generator {
                yield $this->entitiesQueries->addEntityGenerator($entity);
            },
            function () use ($entity, $oldBlock, $oldTag, $newBlock, $newTag, $action, $position): void {
                $this->addRawBlockLog(Utils::getEntityUniqueId($entity), $oldBlock, $oldTag, $newBlock, $newTag, $action, $position);
            }
        );
    }

    final protected function addRawBlockLog(string $uuid, Block $oldBlock, ?CompoundTag $oldTag, Block $newBlock, ?CompoundTag $newTag, Action $action, ?Position $position = null): void
    {
        Await::f2c(
            function () use ($uuid, $oldBlock, $oldTag, $newBlock, $newTag, $action, $position): Generator {
                $pos = $position ?? $newBlock->asPosition();

                /** @var int $lastId */
                $lastId = yield $this->addRawLog($uuid, $pos, $action);

                yield $this->executeInsert(QueriesConst::ADD_BLOCK_LOG, [
                    'log_id' => $lastId,
                    'old_id' => $oldBlock->getId(),
                    'old_meta' => $oldBlock->getDamage(),
                    'old_nbt' => $oldTag !== null ? Utils::serializeNBT($oldTag) : null,
                    'new_id' => $newBlock->getId(),
                    'new_meta' => $newBlock->getDamage(),
                    'new_nbt' => $newTag !== null ? Utils::serializeNBT($newTag) : null
                ]);
            },
            static function (): void {
                //NOOP
            }
        );
    }

    /**
     * It logs the block who made the action for block.
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
        if (count($oldBlocks) === 0) {
            return;
        }

        $oldBlocks = array_map(static function (Block $block): SerializableBlock {
            return SerializableBlock::toSerializableBlock($block);
        }, $oldBlocks);

        if (is_array($newBlocks)) {
            if (count($newBlocks) === 0) {
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

        Await::f2c(
            function () use ($entity, $oldBlocks, $newBlocks, $action) : Generator {
                yield $this->entitiesQueries->addEntityGenerator($entity);

                $logsTask = new LogsQueryGeneratorTask(
                    Utils::getEntityUniqueId($entity),
                    $oldBlocks,
                    $action,
                    function (string $query) use ($oldBlocks, $newBlocks) : Generator {
                        [$firstInsertedId, $affectedRows] = yield $this->executeInsertRaw($query, [], true);

                        if ($this->configParser->isSQLite()) {
                            $firstInsertedId -= $affectedRows - 1;
                        }

                        $blocksTask = new BlocksQueryGeneratorTask(
                            $firstInsertedId,
                            $oldBlocks,
                            $newBlocks,
                            function (string $query): Generator {
                                yield $this->executeInsertRaw($query);
                            }
                        );

                        Server::getInstance()->getAsyncPool()->submitTask($blocksTask);
                    }
                );

                Server::getInstance()->getAsyncPool()->submitTask($logsTask);
            },
            static function (): void {
                //NOOP
            }
        );
    }

    protected function onRollback(bool $rollback, Area $area, CommandParser $commandParser, array $logIds, float $startTime, Closure $onComplete): Generator
    {
        $prefix = $rollback ? 'old' : 'new';
        $inclusions = $commandParser->getBlocks();
        $exclusions = $commandParser->getExclusions();

        /** @var SerializableBlock[] $blocks */
        $blocks = [];

        if ($rollback) {
            $blockRows = yield $this->executeSelect(QueriesConst::GET_ROLLBACK_OLD_BLOCKS, ['log_ids' => $logIds]);
        } else {
            $blockRows = yield $this->executeSelect(QueriesConst::GET_ROLLBACK_NEW_BLOCKS, ['log_ids' => $logIds]);
        }

        foreach ($blockRows as $index => $row) {
            $historyId = (int)$row['history_id'];
            $id = (int)$row["{$prefix}_id"];
            $meta = (int)$row["{$prefix}_meta"];

            if ($inclusions !== null && count($inclusions) > 0) {
                foreach ($inclusions as $inclusion) {
                    if ($inclusion->getId() !== $id && $inclusion->getDamage() !== $meta) {
                        unset($blockRows[$index]);
                        unset($logIds[array_search($historyId, $logIds)]);
                    }
                }
            }

            if ($exclusions !== null && count($exclusions) > 0) {
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

            if (strlen($serializedNBT) > 0) {
                $nbt = Utils::deserializeNBT($serializedNBT);
                $tile = Tile::createTile(BlockUtils::getTileName($block->getId()), $area->getWorld(), $nbt);
                if ($tile !== null) {
                    if ($tile instanceof InventoryHolder && !$this->configParser->getRollbackItems()) {
                        $tile->getInventory()->clearAll();
                    }
                    $area->getWorld()->addTile($tile);
                }
            } else {
                $tile = BlockUtils::asTile($block->toBlock());
                if ($tile !== null) {
                    $area->getWorld()->removeTile($tile);
                }
            }
        }

        Server::getInstance()->getAsyncPool()->submitTask(new RollbackTask($rollback, $area, $commandParser, $blocks, $startTime, $onComplete));
    }
}
