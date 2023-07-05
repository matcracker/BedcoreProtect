<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
 *
 * Copyright (C) 2019-2023
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

use Generator;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\enums\ActionType;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\tasks\async\AsyncBlockSetter;
use matcracker\BedcoreProtect\utils\ArrayUtils;
use matcracker\BedcoreProtect\utils\BlockUtils;
use matcracker\BedcoreProtect\utils\EntityUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\ItemFrame;
use pocketmine\block\Leaves;
use pocketmine\block\tile\ItemFrame as TileItemFrame;
use pocketmine\block\tile\Tile;
use pocketmine\block\tile\TileFactory;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginException;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\World\Position;
use pocketmine\World\World;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Await;
use function array_values;
use function assert;
use function count;
use function get_class;
use function microtime;

/**
 * It contains all the queries methods related to blocks.
 *
 * Class BlocksQueries
 * @package matcracker\BedcoreProtect\storage\queries
 */
class BlocksQueries extends Query
{
    public function __construct(
        protected Main               $plugin,
        protected DataConnector      $connector,
        protected EntitiesQueries    $entitiesQueries,
        protected InventoriesQueries $inventoriesQueries)
    {
        parent::__construct($plugin, $connector);
    }

    /**
     * It logs the entity who makes the action for block.
     *
     * @param Entity|null $entity
     * @param Block $oldBlock
     * @param Block $newBlock
     * @param Action $action
     * @param Position|null $position
     * @param Vector3|null $sourcePos
     */
    public function addBlockLogByEntity(?Entity $entity, Block $oldBlock, Block $newBlock, Action $action, ?Position $position = null, ?Vector3 $sourcePos = null): void
    {
        $oldNbt = BlockUtils::serializeTileTag($oldBlock);
        $newNbt = BlockUtils::serializeTileTag($newBlock);
        $position ??= $newBlock->getPosition();
        $worldName = $position->getWorld()->getFolderName();
        $time = microtime(true);

        Await::f2c(
            function () use ($entity, $oldBlock, $oldNbt, $newBlock, $newNbt, $position, $worldName, $sourcePos, $action, $time): Generator {
                if ($entity !== null) {
                    yield from $this->entitiesQueries->addEntity($entity);
                    $entityUuid = EntityUtils::getUniqueId($entity);
                } else {
                    $entityUuid = null;
                }

                if ($sourcePos !== null) {
                    /** @var string|null $uuid */
                    $uuid = (yield from $this->getUuidByPosition($sourcePos, $worldName)) ?? $entityUuid;
                } else {
                    $uuid = $entityUuid;
                }

                if ($uuid !== null) {
                    yield from $this->addRawBlockLog(
                        $uuid,
                        $oldBlock,
                        $oldNbt,
                        $newBlock,
                        $newNbt,
                        $position,
                        $worldName,
                        $action,
                        $time
                    );
                }
            }
        );
    }

    final protected function addRawBlockLog(string $uuid, Block $oldBlock, ?string $oldNbt, Block $newBlock, ?string $newNbt, Vector3 $position, string $worldName, Action $action, float $time): Generator
    {
        /** @var int $lastId */
        [$lastId] = yield from $this->addRawLog($uuid, $position->floor(), $worldName, $action, $time);

        return yield from $this->connector->asyncInsert(QueriesConst::ADD_BLOCK_LOG, [
            "log_id" => $lastId,
            "old_name" => $oldBlock->getName(),
            "old_state" => BlockUtils::serializeBlock($oldBlock),
            "old_nbt" => $oldNbt,
            "new_name" => $newBlock->getName(),
            "new_state" => BlockUtils::serializeBlock($newBlock),
            "new_nbt" => $newNbt
        ]);
    }

    /**
     * @param Entity $entity
     * @param Block[] $oldBlocks
     * @param Action $action
     * @param int $delay
     */
    final public function addScheduledBlocksLogByEntity(Entity $entity, array $oldBlocks, Action $action, int $delay): void
    {
        if (count($oldBlocks) === 0) {
            return;
        }

        /** @var string[] $oldBlocksNbt */
        $oldBlocksNbt = [];
        foreach ($oldBlocks as $oldBlock) {
            $oldBlocksNbt[] = BlockUtils::serializeTileTag($oldBlock);
        }

        $time = microtime(true);

        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function () use ($entity, $oldBlocks, $oldBlocksNbt, $action, $time): void {
                /** @var Block[] $newBlocks */
                $newBlocks = [];
                /** @var string[]|null[] $newBlocksNbt */
                $newBlocksNbt = [];

                foreach ($oldBlocks as $key => $oldBlock) {
                    $pos = $oldBlock->getPosition();
                    $newBlock = $pos->getWorld()->getBlock($pos, addToCache: false);
                    if ($newBlock->isSameState($oldBlock)) {
                        unset($oldBlocks[$key], $oldBlocksNbt[$key]);
                    } else {
                        $newBlocks[] = $newBlock;
                        $newBlocksNbt[] = BlockUtils::serializeTileTag($newBlock);
                    }
                }

                assert(count($oldBlocks) === count($newBlocks));

                if (count($oldBlocks) === 0) {
                    return;
                }

                ArrayUtils::resetKeys($oldBlocks, $oldBlocksNbt);

                Await::g2c(self::getMutex()->runClosure(
                    function () use ($entity, $oldBlocks, $oldBlocksNbt, $newBlocks, $newBlocksNbt, $action, $time): Generator {
                        yield from $this->entitiesQueries->addEntity($entity);

                        yield from $this->connector->asyncGeneric(QueriesConst::BEGIN_TRANSACTION);
                        $generators = [];
                        foreach ($oldBlocks as $key => $oldBlock) {
                            $oldPosition = $oldBlock->getPosition();

                            $generators[] = $this->addRawBlockLog(
                                EntityUtils::getUniqueId($entity),
                                $oldBlock,
                                $oldBlocksNbt[$key],
                                $newBlocks[$key],
                                $newBlocksNbt[$key],
                                $oldPosition->asVector3(),
                                $oldPosition->getWorld()->getFolderName(),
                                $action,
                                $time
                            );
                        }
                        yield from Await::all($generators);

                        yield from $this->connector->asyncGeneric(QueriesConst::END_TRANSACTION);
                    }
                ));
            }
        ), $delay);
    }

    /**
     * @param Entity $entity
     * @param Block[] $oldBlocks
     * @param Action $action
     * @param Vector3|null $sourcePos
     */
    public function addExplosionLogByEntity(Entity $entity, array $oldBlocks, Action $action, ?Vector3 $sourcePos = null): void
    {
        if (count($oldBlocks) === 0) {
            return;
        }

        $oldBlocks = array_values($oldBlocks);

        /** @var string[] $oldBlocksNbt */
        $oldBlocksNbt = [];
        foreach ($oldBlocks as $oldBlock) {
            $oldBlocksNbt[] = BlockUtils::serializeTileTag($oldBlock);
        }

        $uuidEntity = EntityUtils::getUniqueId($entity);
        $worldName = $entity->getWorld()->getFolderName();
        $time = microtime(true);

        Await::g2c(self::getMutex()->runClosure(
            function () use ($entity, $uuidEntity, $oldBlocks, $oldBlocksNbt, $action, $time, $worldName, $sourcePos): Generator {
                yield from $this->entitiesQueries->addEntity($entity);

                if ($sourcePos !== null) {
                    $uuid = (yield from $this->getUuidByPosition($sourcePos, $worldName)) ?? $uuidEntity;
                } else {
                    $uuid = $uuidEntity;
                }

                yield from $this->connector->asyncGeneric(QueriesConst::BEGIN_TRANSACTION);

                $generators = [];
                foreach ($oldBlocks as $key => $oldBlock) {
                    $oldPos = $oldBlock->getPosition();

                    $generators[] = $this->addRawBlockLog(
                        $uuid,
                        $oldBlock,
                        $oldBlocksNbt[$key],
                        VanillaBlocks::AIR(),
                        null,
                        $oldPos->asVector3(),
                        $oldPos->getWorld()->getFolderName(),
                        $action,
                        $time
                    );
                }
                yield from Await::all($generators);

                yield from $this->connector->asyncGeneric(QueriesConst::END_TRANSACTION);
            }
        ));
    }

    /**
     * It logs the block who made the action for block.
     *
     * @param Block $who
     * @param Block $oldBlock
     * @param Block $newBlock
     * @param Action $action
     * @param Position|null $position
     * @param Vector3|null $sourcePos
     */
    public function addBlockLogByBlock(Block $who, Block $oldBlock, Block $newBlock, Action $action, ?Position $position = null, ?Vector3 $sourcePos = null): void
    {
        $oldNbt = BlockUtils::serializeTileTag($oldBlock);
        $newNbt = BlockUtils::serializeTileTag($newBlock);
        $position ??= $newBlock->getPosition();
        $worldName = $position->getWorld()->getFolderName();
        $time = microtime(true);

        Await::f2c(function () use ($who, $oldBlock, $oldNbt, $newBlock, $newNbt, $action, $time, $position, $worldName, $sourcePos): Generator {
            if ($who instanceof Leaves) {
                $blockUuid = "leaves-uuid";
            } else {
                $blockUuid = "{$who->getName()}-uuid";
            }

            if ($sourcePos !== null) {
                $uuid = (yield from $this->getUuidByPosition($sourcePos, $worldName)) ?? $blockUuid;
            } else {
                $uuid = $blockUuid;
            }

            yield from $this->addRawBlockLog(
                $uuid,
                $oldBlock,
                $oldNbt,
                $newBlock,
                $newNbt,
                $position->asVector3(),
                $worldName,
                $action,
                $time
            );
        });
    }

    /**
     * @param Player $player
     * @param ItemFrame $itemFrame
     * @param Item $item
     * @param Action $action
     */
    public function addItemFrameLogByPlayer(Player $player, ItemFrame $itemFrame, Item $item, Action $action): void
    {
        $tileItemFrame = BlockUtils::asTile($itemFrame->getPosition());
        if ($tileItemFrame === null) {
            $this->plugin->getLogger()->debug("{$player->getName()} tried to interact with invalid Item Frame at {$player->getPosition()}");
            return;
        } elseif (!$tileItemFrame instanceof TileItemFrame) {
            throw new PluginException("Expected ItemFrame tile class, got " . get_class($tileItemFrame));
        }

        $item = clone $item;
        $oldNbt = Utils::serializeNBT($nbt = $tileItemFrame->saveNBT());

        $nbt->setTag(TileItemFrame::TAG_ITEM, $item->nbtSerialize());
        if ($action === ActionType::CLICK()) {
            $nbt->setByte(TileItemFrame::TAG_ITEM_ROTATION, ($itemFrame->getItemRotation() + 1) % ItemFrame::ROTATIONS);
        }
        $newNbt = Utils::serializeNBT($nbt);

        $position = $itemFrame->getPosition();
        $worldName = $position->getWorld()->getFolderName();

        Await::f2c(
            function () use ($player, $item, $itemFrame, $oldNbt, $newNbt, $position, $worldName, $action): Generator {
                yield from $this->addRawBlockLog(
                    EntityUtils::getUniqueId($player),
                    $itemFrame,
                    $oldNbt,
                    $itemFrame,
                    $newNbt,
                    $position,
                    $worldName,
                    $action,
                    microtime(true)
                );

                if ($action !== ActionType::CLICK()) {
                    $this->inventoriesQueries->addItemFrameSlotLog($player, $item, $action, $position, $worldName);
                }
            });
    }

    public function onRollback(CommandSender $sender, World $world, bool $rollback, array $logIds): Generator
    {
        if ($rollback) {
            /** @var array $rows */
            $rows = yield from $this->connector->asyncSelect(QueriesConst::GET_ROLLBACK_OLD_BLOCKS, ["log_ids" => $logIds]);
            $prefix = "old";
        } else {
            /** @var array $rows */
            $rows = yield from $this->connector->asyncSelect(QueriesConst::GET_ROLLBACK_NEW_BLOCKS, ["log_ids" => $logIds]);
            $prefix = "new";
        }

        /** @var int[][] $blockData */
        $blockData = [];
        /** @var string[] $blockData */
        $tilesData = [];
        /** @var string[] $chunks */
        $chunks = [];

        foreach ($rows as $row) {
            $x = (int)$row["x"];
            $y = (int)$row["y"];
            $z = (int)$row["z"];
            $chunkX = $x >> Chunk::COORD_BIT_SIZE;
            $chunkZ = $z >> Chunk::COORD_BIT_SIZE;

            $chunkHash = World::chunkHash($chunkX, $chunkZ);

            if (!isset($chunks[$chunkHash])) {
                if (($chunk = $world->loadChunk($chunkX, $chunkZ)) !== null) {
                    $chunks[$chunkHash] = FastChunkSerializer::serializeTerrain($chunk);
                    $world->unloadChunk($chunkX, $chunkZ, trySave: false);
                } else {
                    $this->plugin->getLogger()->debug("Could not load chunk at [$chunkX;$chunkZ]");
                    continue;
                }
            }

            $blockHash = World::blockHash($x, $y, $z);
            $blockData[$chunkHash][$blockHash] = BlockUtils::deserializeBlock($row["{$prefix}_state"])->getStateId();

            if (isset($row["{$prefix}_nbt"])) {
                $tilesData[$blockHash] = $row["{$prefix}_nbt"];
            }
        }

        $tileFactory = TileFactory::getInstance();
        foreach ($tilesData as $blockHash => $tileData) {
            World::getBlockXYZ($blockHash, $x, $y, $z);

            $world->getTileAt($x, $y, $z)?->close();
            /** @var Tile|null $tile */
            $tile = $tileFactory->createFromData($world, Utils::deserializeNBT($tileData));

            if ($tile !== null) {
                $world->addTile($tile);
            } else {
                $this->plugin->getLogger()->debug("Could not create tile at $x $y $z.");
            }
        }

        $pool = Server::getInstance()->getAsyncPool();

        return yield from Await::promise(
            static fn($resolve, $reject) => $pool->submitTask(new AsyncBlockSetter($world->getFolderName(), $chunks, $blockData, $resolve))
        );
    }
}
