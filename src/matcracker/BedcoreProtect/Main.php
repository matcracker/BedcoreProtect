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

namespace matcracker\BedcoreProtect;

use JackMD\UpdateNotifier\UpdateNotifier;
use matcracker\BedcoreProtect\commands\BCPCommand;
use matcracker\BedcoreProtect\listeners\BlockListener;
use matcracker\BedcoreProtect\listeners\EntityListener;
use matcracker\BedcoreProtect\listeners\PlayerListener;
use matcracker\BedcoreProtect\listeners\WorldListener;
use matcracker\BedcoreProtect\storage\Database;
use matcracker\BedcoreProtect\tasks\SQLiteTransactionTask;
use matcracker\BedcoreProtect\utils\ConfigParser;
use pocketmine\plugin\PluginBase;

final class Main extends PluginBase
{
    public const PLUGIN_NAME = "BedcoreProtect";
    public const PLUGIN_TAG = "[" . self::PLUGIN_NAME . "]";
    public const MESSAGE_PREFIX = "&3" . self::PLUGIN_NAME . " &f- ";

    /**@var Database $database */
    private $database;

    /**@var ConfigParser $configParser */
    private $configParser;
    /**@var ConfigParser $oldConfigParser */
    private $oldConfigParser;

    /**
     * @return Database
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    public function getParsedConfig(): ConfigParser
    {
        return $this->configParser;
    }

    public function restoreConfig(): void
    {
        $this->configParser = $this->oldConfigParser;
    }

    public function reloadConfig(): void
    {
        if ($this->configParser !== null) {
            $this->oldConfigParser = clone $this->configParser;
        }

        parent::reloadConfig();
        $this->configParser = new ConfigParser($this);
        $this->configParser->validate();
    }

    protected function onEnable(): void
    {
        $this->database = new Database($this);

        @mkdir($this->getDataFolder());
        $this->reloadConfig();
        $this->saveResource("bedcore_database.db");

        if (!$this->configParser->isValidConfig()) {
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->configParser->loadData();

        //Database connection
        $this->getLogger()->info("Trying to establishing connection with {$this->configParser->getPrintableDatabaseType()} database...");
        if (!$this->database->connect()) {
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
        $this->getLogger()->info("Connection successfully established.");

        $this->database->getQueries()->init();

        if ($this->configParser->isSQLite()) {
            $this->getScheduler()->scheduleDelayedRepeatingTask(new SQLiteTransactionTask($this->database), SQLiteTransactionTask::getTicks(), SQLiteTransactionTask::getTicks());
        }

        $this->getServer()->getCommandMap()->register("bedcoreprotect", new BCPCommand($this));

        //Registering events
        $events = [
            new BlockListener($this),
            new EntityListener($this),
            new PlayerListener($this),
            new WorldListener($this)
        ];

        foreach ($events as $event) {
            $this->getServer()->getPluginManager()->registerEvents($event, $this);
        }

        if ($this->configParser->getCheckUpdates()) {
            UpdateNotifier::checkUpdate($this);
        }
    }

    protected function onDisable(): void
    {
        $this->getScheduler()->cancelAllTasks();
        $this->database->disconnect();

        Inspector::clearCache();
    }
}