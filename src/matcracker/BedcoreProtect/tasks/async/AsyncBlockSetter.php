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
use matcracker\BedcoreProtect\utils\WorldUtils;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginException;
use pocketmine\scheduler\AsyncTask;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use function array_values;
use function count;
use function morton2d_decode;
use function serialize;
use function unserialize;

final class AsyncBlockSetter extends AsyncTask
{
    private string $serializedFullBlockIds;
    private string $serializedPositions;
    private string $serializedChunks;

    /**
     * RollbackTask constructor.
     * @param int[] $fullBlockIds
     * @param Vector3[] $positions
     * @param string $worldName
     * @param array<int, string> $serializedChunks
     * @param Closure $onComplete
     */
    public function __construct(
        private array  $fullBlockIds,
        array          $positions,
        private string $worldName,
        array          $serializedChunks,
        Closure        $onComplete)
    {
        if (count($fullBlockIds) !== count($positions)) {
            throw new PluginException("The number of full block IDs is not the same of their positions.");
        }

        $this->serializedFullBlockIds = serialize(array_values($fullBlockIds));
        $this->serializedPositions = serialize(array_values($positions));
        $this->serializedChunks = serialize($serializedChunks);

        $this->storeLocal("onComplete", $onComplete);
    }

    public function onRun(): void
    {
        /** @var Chunk[] $chunks */
        $chunks = [];

        foreach (unserialize($this->serializedChunks) as $hash => $serializedChunk) {
            $chunks[$hash] = FastChunkSerializer::deserialize($serializedChunk);
        }

        /** @var Vector3[] $positions */
        $positions = unserialize($this->serializedPositions);

        /**
         * @var int $idx
         * @var int $fullBlockId
         */
        foreach (unserialize($this->serializedFullBlockIds) as $idx => $fullBlockId) {
            $blockPos = $positions[$idx];
            $index = World::chunkHash($blockPos->getX() >> 4, $blockPos->getZ() >> 4);
            if (isset($chunks[$index])) {
                $chunks[$index]->setFullBlock($blockPos->getX() & 0x0f, $blockPos->getY(), $blockPos->getZ() & 0x0f, $fullBlockId);
            }
        }

        $this->setResult($chunks);
    }

    public function onCompletion(): void
    {
        $world = WorldUtils::getNonNullWorldByName($this->worldName);

        /** @var Chunk[] $chunks */
        $chunks = $this->getResult();
        foreach ($chunks as $hash => $chunk) {
            [$cx, $cz] = morton2d_decode($hash);
            $world->setChunk($cx, $cz, $chunk, false);
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
        $onComplete([count($this->fullBlockIds), count($chunks)]);
    }
}
