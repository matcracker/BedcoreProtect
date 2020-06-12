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
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\math\Area;
use matcracker\BedcoreProtect\serializable\SerializableBlock;
use matcracker\BedcoreProtect\tasks\async\BlocksQueryGeneratorTask;
use matcracker\BedcoreProtect\tasks\async\LogsQueryGeneratorTask;
use matcracker\BedcoreProtect\tasks\async\RollbackTask;
use matcracker\BedcoreProtect\utils\BlockUtils;
use matcracker\BedcoreProtect\utils\EntityUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\Leaves;
use pocketmine\entity\Entity;
use pocketmine\inventory\InventoryHolder;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\tile\ItemFrame;
use pocketmine\tile\Tile;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Await;
use function array_map;
use function count;
use function microtime;
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

    public function __construct(Main $plugin, DataConnector $connector, EntitiesQueries $entitiesQueries, InventoriesQueries $inventoriesQueries)
    {
        parent::__construct($plugin, $connector);
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
        $oldNbt = BlockUtils::serializeTileTag($oldBlock);
        $newNbt = BlockUtils::serializeTileTag($newBlock);
        $pos = $position ?? $newBlock->asPosition();
        $worldName = $pos->getLevelNonNull()->getName();
        $time = microtime(true);

        Await::f2c(
            function () use ($entity, $oldBlock, $oldNbt, $newBlock, $newNbt, $pos, $worldName, $action, $time): Generator {
                yield $this->entitiesQueries->addEntityGenerator($entity);
                yield $this->addRawBlockLog(EntityUtils::getUniqueId($entity), $oldBlock, $oldNbt, $newBlock, $newNbt, $pos, $worldName, $action, $time);
            }
        );
    }

    /**
     * @param Entity $entity
     * @param Block[] $oldBlocks
     * @param Action $action
     * @param Closure $onTaskRun
     * @param int $delay
     */
    final public function addScheduledBlocksLogByEntity(Entity $entity, array $oldBlocks, Action $action, Closure $onTaskRun, int $delay): void
    {
        if (count($oldBlocks) === 0) {
            return;
        }

        $time = microtime(true);
        $oldBlocks = array_map(static function (Block $block): SerializableBlock {
            return SerializableBlock::serialize($block);
        }, $oldBlocks);

        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function (int $currentTick) use ($entity, $oldBlocks, $action, $onTaskRun, $time) : void {
                /** @var SerializableBlock[] $newBlocks */
                $newBlocks = $onTaskRun($oldBlocks);

                $this->addSerialBlocksLogByEntity(
                    $entity,
                    $oldBlocks,
                    $newBlocks,
                    $action,
                    $time
                );
            }
        ), $delay);
    }

    final protected function addRawBlockLog(string $uuid, Block $oldBlock, ?string $oldNbt, Block $newBlock, ?string $newNbt, Vector3 $position, string $worldName, Action $action, float $time): Generator
    {
        /** @var int $lastId */
        $lastId = yield $this->addRawLog($uuid, $position, $worldName, $action, $time);

        return yield $this->executeInsert(QueriesConst::ADD_BLOCK_LOG, [
            'log_id' => $lastId,
            'old_id' => $oldBlock->getId(),
            'old_meta' => $oldBlock->getDamage(),
            'old_nbt' => $oldNbt,
            'new_id' => $newBlock->getId(),
            'new_meta' => $newBlock->getDamage(),
            'new_nbt' => $newNbt
        ]);
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
        $name .= "-uuid";
        $oldNbt = BlockUtils::serializeTileTag($oldBlock);
        $newNbt = BlockUtils::serializeTileTag($newBlock);
        $pos = $position ?? $newBlock->asPosition();
        $worldName = $pos->getLevelNonNull()->getName();
        $time = microtime(true);

        Await::f2c(
            function () use ($name, $oldBlock, $oldNbt, $newBlock, $newNbt, $pos, $worldName, $action, $time) : Generator {
                yield $this->addRawBlockLog(
                    $name,
                    $oldBlock,
                    $oldNbt,
                    $newBlock,
                    $newNbt,
                    $pos->asVector3(),
                    $worldName,
                    $action,
                    $time
                );
            }
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
        $item = clone $item;
        $oldNbt = Utils::serializeNBT($nbt = $itemFrame->saveNBT());

        $nbt->setTag($item->nbtSerialize(-1, ItemFrame::TAG_ITEM));
        if ($action->equals(Action::CLICK())) {
            $nbt->setByte(ItemFrame::TAG_ITEM_ROTATION, ($itemFrame->getItemRotation() + 1) % 8);
        }
        $newNbt = Utils::serializeNBT($nbt);

        $itemFrameBlock = $itemFrame->getBlock();
        $position = $itemFrame->asVector3();
        $worldName = $itemFrame->getLevelNonNull()->getName();
        $time = microtime(true);

        Await::f2c(
            function () use ($player, $itemFrameBlock, $oldNbt, $newNbt, $position, $worldName, $action, $time) : Generator {
                yield $this->addRawBlockLog(
                    EntityUtils::getUniqueId($player),
                    $itemFrameBlock,
                    $oldNbt,
                    $itemFrameBlock,
                    $newNbt,
                    $position,
                    $worldName,
                    $action,
                    $time
                );
            },
            function () use ($player, $item, $action, $position, $worldName): void {
                if (!$action->equals(Action::CLICK())) {
                    $this->inventoriesQueries->addItemFrameSlotLog($player, $item, $action, $position, $worldName);
                }
            }
        );
    }

    /**
     * @param Entity $entity
     * @param SerializableBlock[] $oldBlocks
     * @param SerializableBlock[] $newBlocks
     * @param Action $action
     * @param float $time
     */
    protected function addSerialBlocksLogByEntity(Entity $entity, array $oldBlocks, array $newBlocks, Action $action, float $time)
    {
        if (count($oldBlocks) === 0 || count($newBlocks) === 0) {
            return;
        }

        Await::f2c(
            function () use ($entity, $oldBlocks, $newBlocks, $action, $time) : Generator {
                yield $this->entitiesQueries->addEntityGenerator($entity);

                $logsTask = new LogsQueryGeneratorTask(
                    EntityUtils::getUniqueId($entity),
                    $oldBlocks,
                    $action,
                    $time,
                    $this->configParser->isSQLite(),
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

    /**
     * @param Entity $entity
     * @param Block[] $oldBlocks
     * @param Block[] $newBlocks
     * @param Action $action
     */
    public function addBlocksLogByEntity(Entity $entity, array $oldBlocks, array $newBlocks, Action $action): void
    {
        $this->addSerialBlocksLogByEntity(
            $entity,
            array_map(static function (Block $block): SerializableBlock {
                return SerializableBlock::serialize($block);
            }, $oldBlocks),
            array_map(static function (Block $block): SerializableBlock {
                return SerializableBlock::serialize($block);
            }, $newBlocks),
            $action,
            microtime(true)
        );
    }

    protected function onRollback(bool $rollback, Area $area, CommandParser $commandParser, array $logIds, Closure $onComplete): Generator
    {
        /** @var SerializableBlock[] $blocks */
        $blocks = [];

        if ($rollback) {
            $blockRows = yield $this->executeSelect(QueriesConst::GET_ROLLBACK_OLD_BLOCKS, ['log_ids' => $logIds]);
            $prefix = 'old';
        } else {
            $blockRows = yield $this->executeSelect(QueriesConst::GET_ROLLBACK_NEW_BLOCKS, ['log_ids' => $logIds]);
            $prefix = 'new';
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

        Server::getInstance()->getAsyncPool()->submitTask(new RollbackTask($rollback, $area, $commandParser->getSenderName(), $blocks, $onComplete));
    }
}
