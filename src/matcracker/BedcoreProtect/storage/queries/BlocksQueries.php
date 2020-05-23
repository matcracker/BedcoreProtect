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
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\math\Area;
use matcracker\BedcoreProtect\serializable\SerializableBlock;
use matcracker\BedcoreProtect\serializable\SerializableEntity;
use matcracker\BedcoreProtect\serializable\SerializablePosition;
use matcracker\BedcoreProtect\tasks\async\BlocksQueryGeneratorTask;
use matcracker\BedcoreProtect\tasks\async\LogsQueryGeneratorTask;
use matcracker\BedcoreProtect\tasks\async\RollbackTask;
use matcracker\BedcoreProtect\utils\BlockUtils;
use matcracker\BedcoreProtect\utils\ConfigParser;
use matcracker\BedcoreProtect\utils\EntityUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\ItemFrame;
use pocketmine\block\Leaves;
use pocketmine\entity\Entity;
use pocketmine\inventory\InventoryHolder;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\tile\Tile;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Await;
use function array_map;
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
    /** @var InventoriesQueries */
    protected $inventoriesQueries;

    public function __construct(DataConnector $connector, ConfigParser $configParser, EntitiesQueries $entitiesQueries, InventoriesQueries $inventoriesQueries)
    {
        parent::__construct($connector, $configParser);
        $this->entitiesQueries = $entitiesQueries;
        $this->inventoriesQueries = $inventoriesQueries;
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
        $this->addBlockLogBySerializedEntity(SerializableEntity::serialize($entity), $oldBlock, $newBlock, $action, $position);
    }

    /**
     * It logs the entity who makes the action for block.
     *
     * @param SerializableEntity $entity
     * @param Block $oldBlock
     * @param Block $newBlock
     * @param Action $action
     * @param Position|null $position
     */
    public function addBlockLogBySerializedEntity(SerializableEntity $entity, Block $oldBlock, Block $newBlock, Action $action, ?Position $position = null): void
    {
        $oldBlock = SerializableBlock::serialize($oldBlock);
        $newBlock = SerializableBlock::serialize($newBlock);

        Await::f2c(
            function () use ($entity): Generator {
                yield $this->entitiesQueries->addEntityGenerator($entity);
            },
            function () use ($entity, $oldBlock, $newBlock, $action, $position): void {
                $this->addRawBlockLog($entity->getUniqueId(), $oldBlock, $newBlock, $action, $position);
            }
        );
    }

    final protected function addRawBlockLog(string $uuid, SerializableBlock $oldBlock, SerializableBlock $newBlock, Action $action, ?Position $position = null): void
    {
        $position = $position !== null ? SerializablePosition::serialize($position) : $newBlock;

        Await::f2c(
            function () use ($uuid, $oldBlock, $newBlock, $action, $position): Generator {
                /** @var int $lastId */
                $lastId = yield $this->addRawLog($uuid, $position, $action);

                yield $this->executeInsert(QueriesConst::ADD_BLOCK_LOG, [
                    'log_id' => $lastId,
                    'old_id' => $oldBlock->getId(),
                    'old_meta' => $oldBlock->getMeta(),
                    'old_nbt' => $oldBlock->getSerializedNbt(),
                    'new_id' => $newBlock->getId(),
                    'new_meta' => $newBlock->getMeta(),
                    'new_nbt' => $newBlock->getSerializedNbt()
                ]);
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

        $this->addRawBlockLog(
            "{$name}-uuid",
            SerializableBlock::serialize($oldBlock),
            SerializableBlock::serialize($newBlock),
            $action,
            $position
        );
    }

    /**
     * @param Player $player
     * @param ItemFrame $itemFrame
     * @param Item $item
     * @param Action $action
     */
    public function addItemFrameLogByPlayer(Player $player, ItemFrame $itemFrame, Item $item, Action $action): void
    {
        $itemFrame = SerializableBlock::serialize($itemFrame);
        $this->addRawBlockLog(EntityUtils::getUniqueId($player), $itemFrame, $itemFrame, $action);
        $this->inventoriesQueries->addItemFrameSlotLog($player, $item, $action, $itemFrame);
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
            return SerializableBlock::serialize($block);
        }, $oldBlocks);

        if (is_array($newBlocks)) {
            if (count($newBlocks) === 0) {
                return;
            }

            (function (Block ...$_) { //Type-safe check
            })(... $newBlocks);
            $newBlocks = array_map(static function (Block $block): SerializableBlock {
                return SerializableBlock::serialize($block);
            }, $newBlocks);
        } else {
            $newBlocks = SerializableBlock::serialize($newBlocks);
        }

        $entity = SerializableEntity::serialize($entity);
        Await::f2c(
            function () use ($entity, $oldBlocks, $newBlocks, $action) : Generator {
                yield $this->entitiesQueries->addEntityGenerator($entity);

                $logsTask = new LogsQueryGeneratorTask(
                    $entity->getUniqueId(),
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
            }
        );
    }

    protected function onRollback(bool $rollback, Area $area, array $logIds, float $startTime, Closure $onComplete): Generator
    {
        $prefix = $this->getRollbackPrefix($rollback);
        /** @var SerializableBlock[] $blocks */
        $blocks = [];

        if ($rollback) {
            $blockRows = yield $this->executeSelect(QueriesConst::GET_ROLLBACK_OLD_BLOCKS, ['log_ids' => $logIds]);
        } else {
            $blockRows = yield $this->executeSelect(QueriesConst::GET_ROLLBACK_NEW_BLOCKS, ['log_ids' => $logIds]);
        }

        foreach ($blockRows as $row) {
            $serializedNBT = (string)$row["{$prefix}_nbt"];
            $blocks[] = $block = new SerializableBlock("", (int)$row["{$prefix}_id"], (int)$row["{$prefix}_meta"], (int)$row['x'], (int)$row['y'], (int)$row['z'], (string)$row['world_name'], $serializedNBT);

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
                $tile = BlockUtils::asTile($block->unserialize());
                if ($tile !== null) {
                    $area->getWorld()->removeTile($tile);
                }
            }
        }

        Server::getInstance()->getAsyncPool()->submitTask(new RollbackTask($rollback, $area, $blocks, $startTime, $onComplete));
    }
}
