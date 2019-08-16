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
use matcracker\BedcoreProtect\listeners\BlockSniperListener;
use matcracker\BedcoreProtect\listeners\EntityListener;
use matcracker\BedcoreProtect\listeners\PlayerListener;
use matcracker\BedcoreProtect\listeners\WorldListener;
use matcracker\BedcoreProtect\storage\Database;
use matcracker\BedcoreProtect\tasks\SQLiteTransactionTask;
use matcracker\BedcoreProtect\utils\ConfigParser;
use pocketmine\lang\BaseLang;
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
    /**@var BaseLang $baseLang */
    private $baseLang;
    /**@var boolean $bsHooked */
    private $bsHooked = false;

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

    /**
     * It restores an old copy of @see ConfigParser before plugin reload.
     */
    public function restoreParsedConfig(): void
    {
        $this->configParser = $this->oldConfigParser;
    }

    /**
     * Reloads the plugin configuration and returns true if config is valid.
     * @return bool
     */
    public function reloadPlugin(): bool
    {
        $this->oldConfigParser = clone $this->configParser;
        $this->reloadConfig();

        return $this->configParser->validate()->isValidConfig();
    }

    public function onLoad()
    {
        $this->configParser = (new ConfigParser($this->getConfig()))->validate();
        if (!$this->configParser->isValidConfig()) {
            $this->getServer()->getPluginManager()->disablePlugin($this);

            return;
        }

        $this->baseLang = new BaseLang($this->configParser->getLanguage(), $this->getFile() . "resources/languages/");

        if ($this->configParser->getBlockSniperHook()) {
            $bsPlugin = $this->getServer()->getPluginManager()->getPlugin("BlockSniper");
            if ($bsPlugin !== null && $bsPlugin->isEnabled()) {
                $this->getLogger()->info($this->baseLang->translateString("blocksniper.hook.success"));
                $this->bsHooked = true;
            } else {
                $this->getLogger()->warning("Unable to hook BlockSniper. Check if the plugin has been properly enabled.");
            }
        }

        if ($this->configParser->getCheckUpdates()) {
            UpdateNotifier::checkUpdate($this, $this->getName(), $this->getDescription()->getVersion());
        }
    }

    public function onEnable(): void
    {
        $this->database = new Database($this);

        @mkdir($this->getDataFolder());
        $this->saveResource("bedcore_database.db");
        @chmod($this->getDataFolder() . "patches/.patches", 0777);
        $this->saveResource("patches/.patches", true);
        @chmod($this->getDataFolder() . "patches/.patches", 0444);

        //Database connection
        if (!$this->database->connect()) {
            $this->getServer()->getPluginManager()->disablePlugin($this);

            return;
        }
        $version = $this->getVersion();
        $this->database->getQueries()->init($version);
        $dbVersion = $this->database->getVersion();
        if (version_compare($version, $dbVersion) < 0) {
            $this->getLogger()->warning($this->baseLang->translateString("database.version.higher"));
            $this->getServer()->getPluginManager()->disablePlugin($this);

            return;
        }

        if ($this->database->getPatchManager()->patch()) {
            $this->getLogger()->info("Your database is now updated from v{$dbVersion} to v{$version}.");
        }

        if ($this->configParser->isSQLite()) {
            $this->database->getQueries()->beginTransaction();
            $this->getScheduler()->scheduleDelayedRepeatingTask(new SQLiteTransactionTask($this->database), SQLiteTransactionTask::getTicks(), SQLiteTransactionTask::getTicks());
        }

        $this->getServer()->getCommandMap()->register("bedcoreprotect", new BCPCommand($this));

        //Registering events
        $events = [
            new BlockListener($this),
            new EntityListener($this),
            new PlayerListener($this),
            new WorldListener($this),
            new BlockSniperListener($this)
        ];

        foreach ($events as $event) {
            $this->getServer()->getPluginManager()->registerEvents($event, $this);
        }
    }

    public function isBlockSniperHooked(): bool
    {
        return $this->bsHooked;
    }

    /**
     * Returns the plugin version.
     * @return string
     */
    public function getVersion(): string
    {
        return $this->getDescription()->getVersion();
    }

    public function onDisable(): void
    {
        $this->getScheduler()->cancelAllTasks();
        $this->database->disconnect();

        Inspector::clearCache();
    }
}