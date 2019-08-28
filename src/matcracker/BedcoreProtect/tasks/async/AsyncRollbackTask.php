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

use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\math\Area;
use matcracker\BedcoreProtect\serializable\SerializableBlock;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class AsyncRollbackTask extends AsyncTask
{
    /**@var Area $area */
    private $area;
    /**@var SerializableBlock[] $blocks */
    private $blocks;
    /**@var CommandParser $commandParser */
    private $commandParser;
    /**@var string[] $serializedChunks */
    private $serializedChunks;
    /**@var float $startTime */
    private $startTime;

    /**
     * AsyncRollbackTask constructor.
     * @param Area $area
     * @param SerializableBlock[] $blocks
     * @param CommandParser $parser
     * @param float $startTime
     * @param int[] $logIds
     */
    public function __construct(Area $area, array $blocks, CommandParser $parser, float $startTime, array $logIds)
    {
        $this->area = $area;
        $this->serializedChunks = Utils::serializeChunks($area->getTouchedChunks($blocks));
        $this->blocks = $blocks;
        $this->commandParser = $parser;
        $this->startTime = $startTime;
        $this->storeLocal($logIds);
    }

    public function onRun(): void
    {
        $chunks = (array)$this->serializedChunks;

        foreach ($chunks as $hash => $chunkData) {
            $chunks[$hash] = Chunk::fastDeserialize($chunkData);
        }
        /**@var Chunk[] $chunks */
        foreach ($this->blocks as $vector) {
            $index = Level::chunkHash($vector->getX() >> 4, $vector->getZ() >> 4);
            if (isset($chunks[$index])) {
                $chunks[$index]->setBlock((int)$vector->getX() & 0x0f, $vector->getY(), (int)$vector->getZ() & 0x0f, $vector->getId(), $vector->getMeta());
            }
        }
        $this->setResult($chunks);
    }

    public function onCompletion(Server $server): void
    {
        $world = $this->area->getWorld();
        if ($world !== null) {
            /**@var Chunk[] $chunks */
            $chunks = $this->getResult();
            foreach ($chunks as $chunk) {
                $world->setChunk($chunk->getX(), $chunk->getZ(), $chunk, false);
            }

            /**@var Main $plugin */
            $plugin = Server::getInstance()->getPluginManager()->getPlugin(Main::PLUGIN_NAME);
            if ($plugin === null) {
                return;
            }
            $logIds = (array)$this->fetchLocal();
            $plugin->getDatabase()->getQueries()->rollbackEntities($this->isRollback(), $this->area, $this->commandParser, $logIds);
            $plugin->getDatabase()->getQueries()->updateRollbackStatus($this->isRollback(), $logIds);
        }
    }

    protected function isRollback(): bool
    {
        return true;
    }
}