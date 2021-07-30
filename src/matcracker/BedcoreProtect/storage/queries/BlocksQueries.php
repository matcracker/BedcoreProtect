<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
 *
 * Copyright (C) 2019-2021
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
use Closure;
use Generator;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\serializable\SerializableBlock;
use matcracker\BedcoreProtect\tasks\async\RollbackTask;
use matcracker\BedcoreProtect\utils\AwaitMutex;
use matcracker\BedcoreProtect\utils\BlockUtils;
use matcracker\BedcoreProtect\utils\EntityUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\Leaves;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\inventory\InventoryHolder;
use pocketmine\item\Item;
use pocketmine\level\Level;
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
use function array_values;
use function class_exists;
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
    protected EntitiesQueries $entitiesQueries;
    protected InventoriesQueries $inventoriesQueries;
    private AwaitMutex $mutexBlock;

    public function __construct(Main $plugin, DataConnector $connector, EntitiesQueries $entitiesQueries, InventoriesQueries $inventoriesQueries)
    {
        parent::__construct($plugin, $connector);
        $this->entitiesQueries = $entitiesQueries;
        $this->inventoriesQueries = $inventoriesQueries;
        $this->mutexBlock = new AwaitMutex();
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
        $worldName = $pos->getLevelNonNull()->getFolderName();
        $time = microtime(true);

        Await::f2c(
            function () use ($entity, $oldBlock, $oldNbt, $newBlock, $newNbt, $pos, $worldName, $action, $time): Generator {
                yield $this->entitiesQueries->addEntity($entity);
                yield $this->addRawBlockLog(EntityUtils::getUniqueId($entity), $oldBlock, $oldNbt, $newBlock, $newNbt, $pos, $worldName, $action, $time);
            }
        );
    }

    final protected function addRawBlockLog(string $uuid, Block $oldBlock, ?string $oldNbt, Block $newBlock, ?string $newNbt, Vector3 $position, string $worldName, Action $action, float $time): Generator
    {
        /** @var int $lastId */
        $lastId = yield $this->addRawLog($uuid, $position, $worldName, $action, $time);

        return yield $this->executeInsert(QueriesConst::ADD_BLOCK_LOG, [
            "log_id" => $lastId,
            "old_id" => $oldBlock->getId(),
            "old_meta" => $oldBlock->getDamage(),
            "old_nbt" => $oldNbt,
            "new_id" => $newBlock->getId(),
            "new_meta" => $newBlock->getDamage(),
            "new_nbt" => $newNbt
        ]);
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

        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function (int $currentTick) use ($entity, $oldBlocks, $action, $onTaskRun, $time): void {
                /** @var Block[] $newBlocks */
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

    /**
     * @param Entity $entity
     * @param Block[] $oldBlocks
     * @param Block[] $newBlocks
     * @param Action $action
     * @param float $time
     */
    protected function addSerialBlocksLogByEntity(Entity $entity, array $oldBlocks, array $newBlocks, Action $action, float $time): void
    {
        $cntOldBlocks = count($oldBlocks);
        $cntNewBlocks = count($newBlocks);

        if ($cntOldBlocks === 0 || $cntNewBlocks === 0) {
            return;
        } elseif ($cntNewBlocks !== 1 && $cntOldBlocks !== $cntNewBlocks) {
            throw new ArrayOutOfBoundsException("The number of old blocks must be the same as new blocks, or vice-versa. Got $cntOldBlocks <> $cntNewBlocks");
        }

        /** @var SerializableBlock[] $oldBlocks */
        $oldBlocks = array_values(array_map(static function (Block $block): SerializableBlock {
            return SerializableBlock::fromBlock($block);
        }, $oldBlocks));

        /** @var SerializableBlock[] $newBlocks */
        $newBlocks = array_values(array_map(static function (Block $block): SerializableBlock {
            return SerializableBlock::fromBlock($block);
        }, $newBlocks));

        $uuidEntity = EntityUtils::getUniqueId($entity);

        $this->mutexBlock->putClosure(
            function () use ($entity, $uuidEntity, $oldBlocks, $newBlocks, $cntNewBlocks, $action, $time): Generator {
                yield $this->entitiesQueries->addEntity($entity);

                yield $this->executeGeneric(QueriesConst::BEGIN_TRANSACTION);

                if ($cntNewBlocks === 1) {
                    foreach ($oldBlocks as $oldBlock) {
                        yield $this->addRawSerialBlockLog(
                            $uuidEntity,
                            $oldBlock,
                            $newBlocks[0],
                            $oldBlock->asVector3(),
                            $oldBlock->getWorldName(),
                            $action,
                            $time
                        );
                    }
                } else {
                    foreach ($oldBlocks as $key => $oldBlock) {
                        yield $this->addRawSerialBlockLog(
                            $uuidEntity,
                            $oldBlock,
                            $newBlocks[$key],
                            $oldBlock->asVector3(),
                            $oldBlock->getWorldName(),
                            $action,
                            $time
                        );
                    }
                }

                yield $this->executeGeneric(QueriesConst::END_TRANSACTION);
            }
        );
    }

    final protected function addRawSerialBlockLog(string $uuid, SerializableBlock $oldBlock, SerializableBlock $newBlock, Vector3 $position, string $worldName, Action $action, float $time): Generator
    {
        /** @var int $lastId */
        $lastId = yield $this->addRawLog($uuid, $position, $worldName, $action, $time);

        return yield $this->executeInsert(QueriesConst::ADD_BLOCK_LOG, [
            "log_id" => $lastId,
            "old_id" => $oldBlock->getId(),
            "old_meta" => $oldBlock->getMeta(),
            "old_nbt" => $oldBlock->getSerializedNbt(),
            "new_id" => $newBlock->getId(),
            "new_meta" => $newBlock->getMeta(),
            "new_nbt" => $newBlock->getSerializedNbt()
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
            $name = "leaves";
        }
        $name .= "-uuid";
        $pos = $position ?? $newBlock->asPosition();

        Await::g2c($this->addRawBlockLog(
            $name,
            $oldBlock,
            BlockUtils::serializeTileTag($oldBlock),
            $newBlock,
            BlockUtils::serializeTileTag($newBlock),
            $pos->asVector3(),
            $pos->getLevelNonNull()->getFolderName(),
            $action,
            microtime(true)
        ));
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
        $worldName = $itemFrame->getLevelNonNull()->getFolderName();

        Await::g2c(
            $this->addRawBlockLog(
                EntityUtils::getUniqueId($player),
                $itemFrameBlock,
                $oldNbt,
                $itemFrameBlock,
                $newNbt,
                $position,
                $worldName,
                $action,
                microtime(true)
            ),
            function () use ($player, $item, $action, $position, $worldName): void {
                if (!$action->equals(Action::CLICK())) {
                    $this->inventoriesQueries->addItemFrameSlotLog($player, $item, $action, $position, $worldName);
                }
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
            $oldBlocks,
            $newBlocks,
            $action,
            microtime(true)
        );
    }

    public function onRollback(CommandSender $sender, Level $world, bool $rollback, array $logIds): Generator
    {
        /** @var SerializableBlock[] $blocks */
        $blocks = [];

        if ($rollback) {
            $blockRows = yield $this->executeSelect(QueriesConst::GET_ROLLBACK_OLD_BLOCKS, ["log_ids" => $logIds]);
            $prefix = "old";
        } else {
            $blockRows = yield $this->executeSelect(QueriesConst::GET_ROLLBACK_NEW_BLOCKS, ["log_ids" => $logIds]);
            $prefix = "new";
        }

        foreach ($blockRows as $row) {
            $serializedNBT = (string)$row["{$prefix}_nbt"];
            $id = (int)$row["{$prefix}_id"];
            $pos = new Vector3((int)$row["x"], (int)$row["y"], (int)$row["z"]);
            $blocks[] = new SerializableBlock("", $id, (int)$row["{$prefix}_meta"], $pos, (string)$row["world_name"], $serializedNBT);

            if (strlen($serializedNBT) > 0) {
                $cx = $pos->x >> 4;
                $cz = $pos->z >> 4;
                if ($world->loadChunk($cx, $cz, false)) {
                    if (class_exists($tileName = BlockUtils::getTileName($id))) {
                        $tile = Tile::createTile($tileName, $world, Utils::deserializeNBT($serializedNBT));
                        if ($tile instanceof InventoryHolder && !$this->configParser->getRollbackItems()) {
                            $tile->getInventory()->clearAll();
                        }
                    } else {
                        $this->plugin->getLogger()->debug("Could not find tile \"$tileName\".");
                    }
                } else {
                    $this->plugin->getLogger()->debug("Could not load chunk at [$cx;$cz]");
                }
            } else {
                if (($tile = $world->getTile($pos)) !== null) {
                    $world->removeTile($tile);
                }
            }
        }

        Server::getInstance()->getAsyncPool()->submitTask(new RollbackTask($sender->getName(), $world->getFolderName(), $blocks, $rollback, yield));
        yield Await::REJECT;

        return yield Await::ONCE;
    }
}
