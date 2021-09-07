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
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use function count;
use function morton3d_decode;
use function serialize;
use function unserialize;

final class AsyncBlockSetter extends AsyncTask
{
    private string $serializedFullBlockIds;
    private string $serializedChunks;

    /**
     * AsyncBlockSetter constructor.
     * @param array<int, array<int, array<int, int>> $fullBlockIds
     * @param string $worldName
     * @param array<int, string> $serializedChunks
     * @param Closure $onComplete
     */
    public function __construct(
        array          $fullBlockIds,
        private string $worldName,
        array          $serializedChunks,
        Closure        $onComplete
    )
    {
        $this->serializedFullBlockIds = serialize($fullBlockIds);
        $this->serializedChunks = serialize($serializedChunks);

        $this->storeLocal("onComplete", $onComplete);
    }

    public function onRun(): void
    {
        /** @var array<int, string> $serializedChunks */
        $serializedChunks = unserialize($this->serializedChunks);
        /** @var array<int, array<int, array<int, int>>> $fullBlocksIds */
        $fullBlocksIds = unserialize($this->serializedFullBlockIds);

        $cntBlocks = 0;
        /** @var array<int, Chunk> $chunks */
        $chunks = [];
        foreach ($serializedChunks as $chunkHash => $serializedChunk) {
            $chunks[$chunkHash] = $chunk = FastChunkSerializer::deserialize($serializedChunk);
            foreach ($fullBlocksIds[$chunkHash] as $blockHash => $arrFullBlocksIds) {
                foreach ($arrFullBlocksIds as $fullBlockId) {
                    [$x, $y, $z] = morton3d_decode($blockHash);
                    $chunk->setFullBlock($x & 0x0f, $y, $z & 0x0f, $fullBlockId);
                    $cntBlocks++;
                }
            }
        }

        $this->setResult([$cntBlocks, $chunks]);
    }

    public function onCompletion(): void
    {
        $world = Server::getInstance()->getWorldManager()->getWorldByName($this->worldName);
        if ($world === null) {
            //TODO: manage in case of missing world, also see QueryManager::rawRollback()
            return;
        }

        /**
         * @var int $cntBlocks
         * @var array<int, Chunk> $chunks
         */
        [$cntBlocks, $chunks] = $this->getResult();
        foreach ($chunks as $hash => $chunk) {
            World::getXZ($hash, $chunkX, $chunkZ);
            $world->setChunk($chunkX, $chunkZ, $chunk, false);
        }

        //TODO: I need to understand what to do here
        /*foreach ($this->blocks as $block) {
            $pos = $block->asVector3();
            $world->updateAllLight($pos->x, $pos->y, $pos->z);
            foreach ($world->getNearbyEntities(new AxisAlignedBB($pos->x - 1, $pos->y - 1, $pos->z - 1, $pos->x + 2, $pos->y + 2, $pos->z + 2)) as $entity) {
                $entity->onNearbyBlockChange();
            }
            $world->scheduleNeighbourBlockUpdates($pos);
        }*/

        /**@var Closure $onComplete */
        $onComplete = $this->fetchLocal("onComplete");
        $onComplete([$cntBlocks, count($chunks)]);
    }
}
