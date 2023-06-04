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
use pocketmine\math\Vector3;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\thread\NonThreadSafeValue;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\World;
use function count;

final class AsyncBlockSetter extends AsyncTask
{
    private const TLS_KEY_ON_COMPLETION = "onComplete";
    private NonThreadSafeValue $serializedBlockData;
    private NonThreadSafeValue $serializedChunks;

    /**
     * AsyncBlockSetter constructor.
     * @param string $worldName
     * @param string[] $serializedChunks
     * @param int[][] $blockData
     * @param Closure $onComplete
     */
    public function __construct(
        private readonly string $worldName,
        array                   $serializedChunks,
        array                   $blockData,
        Closure                 $onComplete
    )
    {
        $this->serializedBlockData = new NonThreadSafeValue($blockData);
        $this->serializedChunks = new NonThreadSafeValue($serializedChunks);

        $this->storeLocal(self::TLS_KEY_ON_COMPLETION, $onComplete);
    }

    public function onRun(): void
    {
        /** @var string[] $serializedChunks */
        $serializedChunks = $this->serializedChunks->deserialize();
        /** @var int[][] $blockData */
        $blockData = $this->serializedBlockData->deserialize();

        $cntBlocks = 0;
        /** @var Chunk[] $chunks */
        $chunks = [];
        /** @var Vector3[] $blockUpdatePos */
        $blockUpdatePos = [];

        foreach ($serializedChunks as $chunkHash => $serializedChunk) {
            $chunks[$chunkHash] = $chunk = FastChunkSerializer::deserializeTerrain($serializedChunk);
            foreach ($blockData[$chunkHash] as $blockHash => $stateId) {
                World::getBlockXYZ($blockHash, $x, $y, $z);

                if (!isset($blockUpdatePos[$blockHash])) {
                    $blockUpdatePos[$blockHash] = new Vector3($x, $y, $z);
                }

                $chunk->setBlockStateId($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK, $stateId);
                $cntBlocks++;
            }
        }

        $this->setResult([$cntBlocks, $chunks, $blockUpdatePos]);
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
         * @var Chunk[] $chunks
         * @var Vector3[] $blockUpdatePos
         */
        [$cntBlocks, $chunks, $blockUpdatePos] = $this->getResult();
        foreach ($chunks as $chunkHash => $chunk) {
            World::getXZ($chunkHash, $chunkX, $chunkZ);
            $world->setChunk($chunkX, $chunkZ, $chunk);
        }

        /**@var Closure $onComplete */
        $onComplete = $this->fetchLocal(self::TLS_KEY_ON_COMPLETION);
        $onComplete([$cntBlocks, count($chunks), $blockUpdatePos]);
    }
}
