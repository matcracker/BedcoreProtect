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
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\serializable\SerializableBlock;
use matcracker\BedcoreProtect\storage\QueryManager;
use matcracker\BedcoreProtect\utils\WorldUtils;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use Threaded;
use function count;
use function serialize;
use function unserialize;

class RollbackTask extends AsyncTask
{
    protected bool $rollback;
    protected string $worldName;
    protected string $senderName;
    /** @var SerializableBlock[] */
    private array $blocks;
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
    public function __construct(string $senderName, string $worldName, array $blocks, bool $rollback, Closure $onComplete)
    {
        $this->senderName = $senderName;
        $this->worldName = $worldName;
        $this->rollback = $rollback;
        $this->blocks = $blocks;
        $this->serializedBlocks = serialize($blocks);

        $this->serializedChunks = new Threaded();
        foreach (WorldUtils::getChunks(WorldUtils::getNonNullWorldByName($worldName), $blocks) as $hash => $chunk) {
            $this->serializedChunks[] = serialize([$hash, $chunk->fastSerialize()]);
        }

        $this->storeLocal($onComplete);
    }

    public function onRun(): void
    {
        /** @var Chunk[] $chunks */
        $chunks = [];

        foreach ($this->serializedChunks as $serializedChunk) {
            [$hash, $serialChunk] = unserialize($serializedChunk);
            $chunks[$hash] = Chunk::fastDeserialize($serialChunk);
        }

        foreach (unserialize($this->serializedBlocks) as $block) {
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

        $lang = Main::getInstance()->getLanguage();
        QueryManager::addReportMessage($this->senderName, $lang->translateString("rollback.blocks", [count($this->blocks)]));
        //Set the execution time to 0 to avoid duplication of time in the same operation.
        QueryManager::addReportMessage($this->senderName, $lang->translateString("rollback.modified-chunks", [count($chunks)]));

        $onComplete();
    }

    /**
     * @return SerializableBlock[]
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }
}
