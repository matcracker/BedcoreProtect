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

namespace matcracker\BedcoreProtect\tasks\async;

use Closure;
use matcracker\BedcoreProtect\serializable\SerializableBlock;
use matcracker\BedcoreProtect\storage\QueryManager;
use matcracker\BedcoreProtect\utils\Utils;
use matcracker\BedcoreProtect\utils\WorldUtils;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use function count;

class RollbackTask extends AsyncTask
{
    /** @var bool */
    protected $rollback;
    /** @var string */
    protected $worldName;
    /** @var string */
    protected $senderName;
    /** @var SerializableBlock[] */
    protected $blocks;
    /** @var string[] */
    private $serializedChunks;

    /**
     * RollbackTask constructor.
     * @param bool $rollback
     * @param string $worldName
     * @param string $senderName
     * @param SerializableBlock[] $blocks
     * @param Closure $onComplete
     */
    public function __construct(bool $rollback, string $worldName, string $senderName, array $blocks, Closure $onComplete)
    {
        $this->rollback = $rollback;
        $this->worldName = $worldName;
        $this->senderName = $senderName;
        $this->blocks = $blocks;

        $this->serializedChunks = Utils::serializeChunks(WorldUtils::getChunks(WorldUtils::getNonNullWorldByName($worldName), $blocks));
        $this->storeLocal($onComplete);
    }

    public function onRun(): void
    {
        /** @var Chunk[] $chunks */
        $chunks = [];

        foreach ($this->serializedChunks as $hash => $chunkData) {
            $chunks[$hash] = Chunk::fastDeserialize($chunkData);
        }

        foreach ($this->blocks as $block) {
            $index = Level::chunkHash($block->getX() >> 4, $block->getZ() >> 4);
            if (isset($chunks[$index])) {
                $chunks[$index]->setBlock($block->getX() & 0x0f, $block->getY(), $block->getZ() & 0x0f, $block->getId(), $block->getMeta());
            }
        }

        $this->setResult($chunks);
    }

    public function onCompletion(Server $server): void
    {
        $world = WorldUtils::getNonNullWorldByName($this->worldName);

        /** @var Chunk[] $chunks */
        $chunks = $this->getResult();
        foreach ($chunks as $chunk) {
            $world->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
        }

        foreach ($this->blocks as $block) {
            $pos = $block->asVector3();
            $world->updateAllLight($pos);
            foreach ($world->getNearbyEntities(new AxisAlignedBB($pos->x - 1, $pos->y - 1, $pos->z - 1, $pos->x + 2, $pos->y + 2, $pos->z + 2)) as $entity) {
                $entity->onNearbyBlockChange();
            }
            $world->scheduleNeighbourBlockUpdates($pos);
        }

        /**@var Closure $onComplete */
        $onComplete = $this->fetchLocal();

        QueryManager::addReportMessage($this->senderName, "rollback.blocks", [count($this->blocks)]);
        //Set the execution time to 0 to avoid duplication of time in the same operation.
        QueryManager::addReportMessage($this->senderName, "rollback.modified-chunks", [count($chunks)]);

        $onComplete();
    }

}
