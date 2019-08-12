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

namespace matcracker\BedcoreProtect\tasks\async;

use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\primitive\PrimitiveBlock;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class AsyncLogsQueryGenerator extends AsyncTask
{
    /**@var string $uuid */
    private $uuid;
    /**@var PrimitiveBlock[] $blocks */
    private $blocks;
    /**@var Action $action */
    private $action;
    /**@var AsyncTask|null $nextTask */
    private $nextTask;

    public function __construct(string $uuid, array $blocks, Action $action, ?AsyncTask $nextTask = null)
    {
        $this->uuid = $uuid;
        $this->blocks = $blocks;
        $this->action = $action;
        $this->nextTask = $nextTask;
    }

    public function onRun(): void
    {
        $query = /**@lang text */
            "INSERT INTO log_history(who, x, y, z, world_name, action) VALUES";

        foreach ($this->blocks as $block) {
            $x = $block->getX();
            $y = $block->getY();
            $z = $block->getZ();
            $query .= "((SELECT uuid FROM entities WHERE uuid = '{$this->uuid}'), '{$x}', '{$y}', '{$z}', '{$block->getWorldName()}', '{$this->action->getType()}'),";
        }

        $query = mb_substr($query, 0, -1) . ";";
        $this->setResult($query);
    }

    public function onCompletion(Server $server): void
    {
        /**@var Main $plugin */
        $plugin = Server::getInstance()->getPluginManager()->getPlugin(Main::PLUGIN_NAME);
        if ($plugin === null) {
            return;
        }
        $plugin->getDatabase()->getQueries()->insertRaw((string)$this->getResult(), function () {
            if ($this->nextTask !== null) {
                Server::getInstance()->getAsyncPool()->submitTask($this->nextTask);
            }
        });
    }
}