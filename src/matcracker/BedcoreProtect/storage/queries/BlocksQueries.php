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

use Closure;
use Generator;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\tasks\async\AsyncBlockSetter;
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
use pocketmine\inventory\InventoryHolder;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginException;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\World\Position;
use pocketmine\World\World;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Await;
use function array_values;
use function count;
use function microtime;
use function strlen;
use function var_dump;

/**
 * It contains all the queries methods related to blocks.
 *
 * Class BlocksQueries
 * @package matcracker\BedcoreProtect\storage\queries
 */
class BlocksQueries extends Query
{
    public function __construct(
        Main                         $plugin,
        DataConnector                $connector,
        protected EntitiesQueries    $entitiesQueries,
        protected InventoriesQueries $inventoriesQueries)
    {
        parent::__construct($plugin, $connector);
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
        $pos = $position ?? $newBlock->getPos();
        $worldName = $pos->getWorld()->getFolderName();
        $time = microtime(true);

        Await::f2c(
            function () use ($entity, $oldBlock, $oldNbt, $newBlock, $newNbt, $pos, $worldName, $action, $time): Generator {
                yield $this->entitiesQueries->addEntity($entity);
                yield $this->addRawBlockLog(EntityUtils::getUniqueId($entity), $oldBlock->getFullId(), $oldNbt, $newBlock->getFullId(), $newNbt, $pos, $worldName, $action, $time);
            }
        );
    }

    final protected function addRawBlockLog(string $uuid, int $oldFullBlockId, ?string $oldNbt, int $newFullBlockId, ?string $newNbt, Vector3 $position, string $worldName, Action $action, float $time): Generator
    {
        /** @var int $lastId */
        $lastId = yield $this->addRawLog($uuid, $position->floor(), $worldName, $action, $time);

        return yield $this->executeInsert(QueriesConst::ADD_BLOCK_LOG, [
            "log_id" => $lastId,
            "old_id" => $oldFullBlockId,
            "old_nbt" => $oldNbt,
            "new_id" => $newFullBlockId,
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
            function () use ($entity, $oldBlocks, $action, $onTaskRun, $time): void {
                /** @var Block[] $newBlocks */
                $newBlocks = $onTaskRun($oldBlocks);

                $this->addBlocksLogByEntity(
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
     * @param Action $action
     */
    public function addExplosionLogByEntity(Entity $entity, array $oldBlocks, Action $action): void
    {
        $cntOldBlocks = count($oldBlocks);

        if ($cntOldBlocks === 0) {
            return;
        }

        $oldBlocks = array_values($oldBlocks);

        $uuidEntity = EntityUtils::getUniqueId($entity);
        $time = microtime(true);

        self::getMutex()->putClosure(
            function () use ($entity, $uuidEntity, $oldBlocks, $cntOldBlocks, $action, $time): Generator {
                yield $this->entitiesQueries->addEntity($entity);

                yield $this->executeGeneric(QueriesConst::BEGIN_TRANSACTION);

                $airFullId = VanillaBlocks::AIR()->getFullId();

                for ($i = 0; $i < $cntOldBlocks; $i++) {
                    yield $this->addRawBlockLog(
                        $uuidEntity,
                        $oldBlocks[$i]->getFullId(),
                        BlockUtils::serializeTileTag($oldBlocks[$i]),
                        $airFullId,
                        null,
                        $oldBlocks[$i]->getPos()->asVector3(),
                        $oldBlocks[$i]->getPos()->getWorld()->getFolderName(),
                        $action,
                        $time
                    );
                }

                yield $this->executeGeneric(QueriesConst::END_TRANSACTION);
            }
        );
    }

    /**
     * @param Entity $entity
     * @param Block[] $oldBlocks
     * @param Block[] $newBlocks
     * @param Action $action
     * @param float $time
     */
    public function addBlocksLogByEntity(Entity $entity, array $oldBlocks, array $newBlocks, Action $action, float $time): void
    {
        $cntOldBlocks = count($oldBlocks);
        $cntNewBlocks = count($newBlocks);

        if ($cntOldBlocks === 0 || $cntNewBlocks === 0) {
            return;
        } elseif ($cntOldBlocks !== $cntNewBlocks) {
            throw new PluginException("The number of old blocks must be the same as new blocks, or vice-versa. Got $cntOldBlocks <> $cntNewBlocks");
        }

        $oldBlocks = array_values($oldBlocks);
        $newBlocks = array_values($newBlocks);

        $uuidEntity = EntityUtils::getUniqueId($entity);

        self::getMutex()->putClosure(
            function () use ($entity, $uuidEntity, $oldBlocks, $newBlocks, $cntOldBlocks, $action, $time): Generator {
                yield $this->entitiesQueries->addEntity($entity);

                yield $this->executeGeneric(QueriesConst::BEGIN_TRANSACTION);

                for ($i = 0; $i < $cntOldBlocks; $i++) {
                    yield $this->addRawBlockLog(
                        $uuidEntity,
                        $oldBlocks[$i]->getFullId(),
                        BlockUtils::serializeTileTag($oldBlocks[$i]),
                        $newBlocks[$i]->getFullId(),
                        BlockUtils::serializeTileTag($newBlocks[$i]),
                        $oldBlocks[$i]->getPos()->asVector3(),
                        $oldBlocks[$i]->getPos()->getWorld()->getFolderName(),
                        $action,
                        $time
                    );
                }

                yield $this->executeGeneric(QueriesConst::END_TRANSACTION);
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
        //Particular blocks
        if ($who instanceof Leaves) {
            $name = "leaves-uuid";
        } else {
            $name = "{$who->getName()}-uuid";
        }
        $pos = $position ?? $newBlock->getPos();

        Await::g2c($this->addRawBlockLog(
            $name,
            $oldBlock->getFullId(),
            BlockUtils::serializeTileTag($oldBlock),
            $newBlock->getFullId(),
            BlockUtils::serializeTileTag($newBlock),
            $pos->asVector3(),
            $pos->getWorld()->getFolderName(),
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
        $tileItemFrame = BlockUtils::asTile($itemFrame->getPos());
        if (!$tileItemFrame instanceof TileItemFrame) {
            return;
        }
        $item = clone $item;
        $oldNbt = Utils::serializeNBT($nbt = $tileItemFrame->saveNbt());
        var_dump($nbt);

        $nbt->setTag(TileItemFrame::TAG_ITEM, $item->nbtSerialize());
        if ($action->equals(Action::CLICK())) {
            $nbt->setByte(TileItemFrame::TAG_ITEM_ROTATION, ($itemFrame->getItemRotation() + 1) % ItemFrame::ROTATIONS);
        }
        $newNbt = Utils::serializeNBT($nbt);
        $itemFrame->writeStateToWorld();

        $position = $itemFrame->getPos();
        $worldName = $position->getWorld()->getFolderName();

        Await::g2c(
            $this->addRawBlockLog(
                EntityUtils::getUniqueId($player),
                $itemFrame->getFullId(),
                $oldNbt,
                $itemFrame->getFullId(),
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

    public function onRollback(CommandSender $sender, World $world, bool $rollback, array $logIds): Generator
    {
        if ($rollback) {
            $blockRows = yield $this->executeSelect(QueriesConst::GET_ROLLBACK_OLD_BLOCKS, ["log_ids" => $logIds]);
            $prefix = "old";
        } else {
            $blockRows = yield $this->executeSelect(QueriesConst::GET_ROLLBACK_NEW_BLOCKS, ["log_ids" => $logIds]);
            $prefix = "new";
        }

        /** @var int[] $fullBlockIds */
        $fullBlockIds = [];
        /** @var Vector3[] $blocksPosition */
        $blocksPosition = [];
        /** @var string[] $chunks */
        $chunks = [];

        foreach ($blockRows as $row) {
            $fullBlockIds[] = (int)$row["{$prefix}_id"];
            $blocksPosition[] = $currBlockPos = new Vector3((int)$row["x"], (int)$row["y"], (int)$row["z"]);
            $serializedNBT = (string)$row["{$prefix}_nbt"];

            $chunkX = $currBlockPos->getX() >> 4;
            $chunkZ = $currBlockPos->getZ() >> 4;

            $chunk = $world->getOrLoadChunkAtPosition($currBlockPos);
            if ($chunk !== null) {
                $chunks[World::chunkHash($chunkX, $chunkZ)] = FastChunkSerializer::serialize($chunk);
            } else {
                $this->plugin->getLogger()->debug("Could not load chunk at [$chunkX;$chunkZ]");
                continue;
            }

            $tile = $world->getTile($currBlockPos);

            if (strlen($serializedNBT) > 0) {
                $nbt = Utils::deserializeNBT($serializedNBT);

                //This is necessary to prevent multiple tiles in the same position.
                if ($tile === null) {
                    /** @var Tile|null $tile */
                    $tile = TileFactory::getInstance()->createFromData($world, $nbt);
                    if ($tile !== null) {
                        $world->addTile($tile);
                    }
                } else {
                    $tile->readSaveData($nbt);
                }

                if ($tile !== null) {
                    if ($tile instanceof InventoryHolder && !$this->configParser->getRollbackItems()) {
                        $tile->getInventory()->clearAll();
                    }
                } else {
                    $this->plugin->getLogger()->debug("Could not create tile at $currBlockPos.");
                }
            } else {
                if ($tile !== null) {
                    $world->removeTile($tile);
                }
            }
        }

        Server::getInstance()->getAsyncPool()->submitTask(new AsyncBlockSetter(
            $fullBlockIds,
            $blocksPosition,
            $world->getFolderName(),
            $chunks,
            yield
        ));

        yield Await::REJECT;

        return yield Await::ONCE;
    }
}
