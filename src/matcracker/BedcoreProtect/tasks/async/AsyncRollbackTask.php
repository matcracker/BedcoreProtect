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
            //$configParser = $plugin->getParsedConfig();
            $queries = $plugin->getDatabase()->getQueries();

            $rollback = $this->isRollback();
            if ($rollback) {
                $queries->rollbackEntities($this->area, $this->commandParser, (array)$this->fetchLocal());
            } else {
                $queries->restoreEntities($this->area, $this->commandParser, (array)$this->fetchLocal());
            }
            /*$items = 0;
            $entities = 0;
            if ($configParser->getRollbackItems()) {
                $rollback ? $queries->rollbackItems($this->area, $this->commandParser) : $queries->restoreItems($this->area, $this->commandParser);
            }

            if ($configParser->getRollbackEntities()) {
                $rollback ? $queries->rollbackEntities($this->area, $this->commandParser) : $queries->restoreEntities($this->area, $this->commandParser);
            }
            $duration = round(microtime(true) - $this->startTime, 2);

            $queries->updateRollbackStatus($rollback, (array)$this->fetchLocal());

            if (($sender = $server->getPlayer($this->commandParser->getSenderName())) !== null) {
                $date = Utils::timeAgo(time() - $this->commandParser->getTime());
                $lang = $plugin->getLanguage();

                $sender->sendMessage(Utils::translateColors("&f------"));
                $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString(($rollback ? "rollback" : "restore") . ".completed", [$world->getName()])));
                $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString(($rollback ? "rollback" : "restore") . ".date", [$date])));
                $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString("rollback.radius", [$this->commandParser->getRadius()])));
                $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString("rollback.blocks", [count($this->blocks)])));
                if ($items > 0) {
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString("rollback.items", [$items])));
                }
                if ($entities > 0) {
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString("rollback.entities", [$entities])));
                }

                $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString("rollback.modified-chunks", [count($chunks)])));
                $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString("rollback.time-taken", [$duration])));
                $sender->sendMessage(Utils::translateColors("&f------"));
            }*/
        }
    }

    protected function isRollback(): bool
    {
        return true;
    }
}