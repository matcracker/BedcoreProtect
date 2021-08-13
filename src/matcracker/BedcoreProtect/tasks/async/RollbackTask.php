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

namespace matcracker\BedcoreProtect\tasks\async;

use Closure;
use matcracker\BedcoreProtect\serializable\SerializableBlock;
use matcracker\BedcoreProtect\utils\WorldUtils;
use pocketmine\math\AxisAlignedBB;
use pocketmine\network\mcpe\serializer\ChunkSerializer;
use pocketmine\scheduler\AsyncTask;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use Threaded;
use function count;
use function serialize;
use function unserialize;

class RollbackTask extends AsyncTask
{
    private string $serializedBlocks;
    private Threaded $serializedChunks;

    /**
     * RollbackTask constructor.
     * @param string $senderName
     * @param string $worldName
     * @param SerializableBlock[] $blocks
     * @param bool $rollback
     * @param Closure $onComplete
     */
    public function __construct(
        protected string $senderName,
        protected string $worldName,
        private array $blocks,
        protected bool $rollback,
        Closure $onComplete)
    {
        $this->serializedBlocks = serialize($blocks);

        $this->serializedChunks = new Threaded();
        foreach (WorldUtils::getChunks(WorldUtils::getNonNullWorldByName($worldName), $blocks) as $hash => $chunk) {
            $this->serializedChunks[] = serialize([$hash, ChunkSerializer::serializeFullChunk($chunk)]);
        }

        $this->storeLocal("onComplete", $onComplete);
    }

    public function onRun(): void
    {
        /** @var Chunk[] $chunks */
        $chunks = [];

        foreach ($this->serializedChunks as $serializedChunk) {
            [$hash, $serialChunk] = unserialize($serializedChunk);
            $chunks[$hash] = World::fastDeserialize($serialChunk);
        }

        /** @var SerializableBlock $block */
        foreach (unserialize($this->serializedBlocks) as $block) {
            $index = World::chunkHash($block->getX() >> 4, $block->getZ() >> 4);
            if (isset($chunks[$index])) {
                $chunks[$index]->setFullBlock($block->getX(), $block->getY(), $block->getZ(), $block->getId());
            }
        }

        $this->setResult($chunks);
    }

    public function onCompletion(): void
    {
        $world = WorldUtils::getNonNullWorldByName($this->worldName);

        /** @var Chunk[] $chunks */
        $chunks = $this->getResult();
        foreach ($chunks as $chunk) {
            $world->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
        }

        foreach ($this->blocks as $block) {
            $pos = $block->asVector3();
            $world->updateAllLight($pos->x, $pos->y, $pos->z);
            foreach ($world->getNearbyEntities(new AxisAlignedBB($pos->x - 1, $pos->y - 1, $pos->z - 1, $pos->x + 2, $pos->y + 2, $pos->z + 2)) as $entity) {
                $entity->onNearbyBlockChange();
            }
            $world->scheduleNeighbourBlockUpdates($pos);
        }

        /**@var Closure $onComplete */
        $onComplete = $this->fetchLocal("onComplete");
        $onComplete([count($this->blocks), count($chunks)]);
    }

    /**
     * @return SerializableBlock[]
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }
}
