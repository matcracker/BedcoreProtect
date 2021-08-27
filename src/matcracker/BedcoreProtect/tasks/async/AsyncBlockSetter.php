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
use matcracker\BedcoreProtect\utils\ArrayUtils;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginException;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use function count;
use function serialize;
use function unserialize;

final class AsyncBlockSetter extends AsyncTask
{
    private string $serializedFullBlockIds;
    private string $serializedPositions;
    private string $serializedChunks;

    /**
     * AsyncBlockSetter constructor.
     * @param array<int, array<int, int>> $fullBlockIds
     * @param array<int, array<int, Vector3>> $positions
     * @param string $worldName
     * @param array<int, string> $serializedChunks
     * @param Closure $onComplete
     */
    public function __construct(
        array          $fullBlockIds,
        array          $positions,
        private string $worldName,
        array          $serializedChunks,
        Closure        $onComplete)
    {
        if (!ArrayUtils::checkSameDimension($fullBlockIds, $positions)) {
            throw new PluginException("The number of full block IDs is not the same of their positions.");
        }

        $this->serializedFullBlockIds = serialize($fullBlockIds);
        $this->serializedPositions = serialize($positions);
        $this->serializedChunks = serialize($serializedChunks);

        $this->storeLocal("onComplete", $onComplete);
    }

    public function onRun(): void
    {
        /** @var array<int, string> $serializedChunks */
        $serializedChunks = unserialize($this->serializedChunks);
        /** @var array<int, array<int, int>> $fullBlocksIds */
        $fullBlocksIds = unserialize($this->serializedFullBlockIds);
        /** @var array<int, array<int, Vector3>> $positions */
        $positions = unserialize($this->serializedPositions);

        $blocks = 0;
        /** @var array<int, Chunk> $chunks */
        $chunks = [];
        foreach ($serializedChunks as $hash => $serializedChunk) {
            $chunks[$hash] = $chunk = FastChunkSerializer::deserialize($serializedChunk);
            foreach ($fullBlocksIds[$hash] as $key => $fullBlockId) {
                $pos = $positions[$hash][$key];
                $chunk->setFullBlock($pos->getX() & 0x0f, $pos->getY(), $pos->getZ() & 0x0f, $fullBlockId);
                $blocks++;
            }
        }

        $this->setResult([$chunks, $blocks]);
    }

    public function onCompletion(): void
    {
        $world = Server::getInstance()->getWorldManager()->getWorldByName($this->worldName);
        if ($world === null) {
            //TODO: manage in case of missing world, also see QueryManager::rawRollback()
            return;
        }

        /** @var array<int, Chunk> $chunks */
        [$chunks, $blocks] = $this->getResult();
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
        $onComplete([$blocks, count($chunks)]);
    }
}
