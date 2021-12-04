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

namespace matcracker\BedcoreProtect;

use JackMD\UpdateNotifier\UpdateNotifier;
use matcracker\BedcoreProtect\commands\BCPCommand;
use matcracker\BedcoreProtect\config\ConfigParser;
use matcracker\BedcoreProtect\config\ConfigUpdater;
use matcracker\BedcoreProtect\listeners\BedcoreListener;
use matcracker\BedcoreProtect\listeners\BlockListener;
use matcracker\BedcoreProtect\listeners\EntityListener;
use matcracker\BedcoreProtect\listeners\InspectorListener;
use matcracker\BedcoreProtect\listeners\PlayerListener;
use matcracker\BedcoreProtect\listeners\WorldListener;
use matcracker\BedcoreProtect\storage\DatabaseManager;
use pocketmine\lang\Language;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use function mkdir;
use function version_compare;

final class Main extends PluginBase
{
    public const PLUGIN_NAME = "BedcoreProtect";
    public const MESSAGE_PREFIX = TextFormat::DARK_AQUA . self::PLUGIN_NAME . TextFormat::WHITE . " - ";

    private static Main $instance;
    private Language $language;
    private DatabaseManager $database;
    private ConfigParser $configParser;
    private ConfigParser $oldConfigParser;
    private BCPCommand $bcpCommand;
    /** @var BedcoreListener[] */
    private array $events;

    public static function getInstance(): Main
    {
        return self::$instance;
    }

    public function getLanguage(): Language
    {
        return $this->language;
    }

    public function getDatabase(): DatabaseManager
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
     * Reloads the plugin configuration.
     */
    public function reloadPlugin(): void
    {
        $this->oldConfigParser = $this->configParser;
        $this->reloadConfig();
        $this->configParser = new ConfigParser($this->getConfig());
        $this->database->reloadConfiguration();

        foreach ($this->events as $event) {
            $event->config = $this->configParser;
        }
        $this->language = new Language($this->configParser->getLanguage(), $this->getFile() . "resources/languages/");

        $this->bcpCommand->updateTranslations();
    }

    public function onLoad(): void
    {
        self::$instance = $this;

        @mkdir($this->getDataFolder());

        $confUpdater = new ConfigUpdater($this);

        if ($confUpdater->checkUpdate()) {
            if (!$confUpdater->update()) {
                $this->getLogger()->critical("Could not save the new configuration file.");
            }
        }

        $this->configParser = new ConfigParser($this->getConfig());
        $this->language = new Language($this->configParser->getLanguage(), $this->getFile() . "resources/languages/");

        $this->saveResource($this->configParser->getDatabaseFileName());

        if ($this->configParser->getCheckUpdates()) {
            UpdateNotifier::checkUpdate($this->getName(), $this->getDescription()->getVersion());
        }
    }

    public function onEnable(): void
    {
        $this->database = new DatabaseManager($this);

        $pluginManager = $this->getServer()->getPluginManager();
        //Database connection
        if (!$this->database->connect()) {
            $pluginManager->disablePlugin($this);
            return;
        }

        $queryManager = $this->database->getQueryManager();

        $version = $this->getVersion();
        $queryManager->init($version);
        $dbVersion = $this->database->getVersion();

        if (version_compare($version, $dbVersion) < 0) {
            $this->getLogger()->warning($this->language->translateString("database.version.higher"));
            $pluginManager->disablePlugin($this);
            return;
        }

        if (($lastPatch = $this->database->getPatchManager()->patch()) !== null) {
            $this->getLogger()->info($this->language->translateString("database.version.updated", [$dbVersion, $lastPatch]));
        }

        $queryManager->setupDefaultData();

        if ($this->configParser->isSQLite()) {
            static $hourTicks = 20 * 60 * 60 * 8;
            $this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(
                function (): void {
                    $this->database->optimize();
                }
            ), $hourTicks, $hourTicks);
        }

        $this->bcpCommand = new BCPCommand($this);
        $this->getServer()->getCommandMap()->register("bedcoreprotect", $this->bcpCommand);

        //Registering events
        $this->events = [
            new BlockListener($this),
            new EntityListener($this),
            new PlayerListener($this),
            new WorldListener($this),
            new InspectorListener($this)
        ];

        foreach ($this->events as $event) {
            $pluginManager->registerEvents($event, $this);
        }
    }

    /**
     * Returns the plugin version.
     */
    public function getVersion(): string
    {
        return $this->getDescription()->getVersion();
    }

    public function onDisable(): void
    {
        $this->getScheduler()->cancelAllTasks();
        $this->database->disconnect();

        Inspector::removeAll();
    }
}
