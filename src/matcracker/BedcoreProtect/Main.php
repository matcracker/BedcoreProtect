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

use matcracker\BedcoreProtect\commands\BCPCommand;
use matcracker\BedcoreProtect\listeners\TrackerListener;
use matcracker\BedcoreProtect\storage\Database;
use matcracker\BedcoreProtect\utils\ConfigParser;
use pocketmine\plugin\PluginBase;
use UnexpectedValueException;

final class Main extends PluginBase
{
    public const PLUGIN_NAME = "BedcoreProtect";
    public const MESSAGE_PREFIX = "&3" . self::PLUGIN_NAME . " &f- ";


    /**@var Database */
    private $database;

    public function onEnable(): void
    {
        if (!extension_loaded("curl")) {
            $this->getLogger()->error("Extension 'curl' is missing. Enable it on php.ini file.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }

        if (!$this->isPhar()) {
            $this->getLogger()->warning("/-------------------------------<!WARNING!>--------------------------------\\");
            $this->getLogger()->warning("|         It is not recommended to run BedcoreProtect from source.         |");
            $this->getLogger()->warning("|You can get a packaged release at https://poggit.pmmp.io/p/BedcoreProtect/|");
            $this->getLogger()->warning("\--------------------------------------------------------------------------/");
        }

        include_once($this->getFile() . "/vendor/autoload.php");

        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();

        $validation = $this->getParsedConfig()->validateConfig();
        if (!$validation->isValid()) {
            $this->getLogger()->warning("Configuration's file is not correct.");
            foreach ($validation->getFailures() as $failure) {
                $this->getLogger()->warning($failure->format());
            }
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->database = new Database($this);

        if (!$this->database->isConnected()) {
            $this->getLogger()->alert("Could not connect to the database.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->database->getQueries()->init();

        $this->getServer()->getCommandMap()->register("bedcoreprotect", new BCPCommand($this));

        $this->getServer()->getPluginManager()->registerEvents(new TrackerListener($this), $this);
    }

    public function getParsedConfig(): ConfigParser
    {
        return new ConfigParser($this);
    }

    /**
     * @return Database
     */
    public function getDatabase(): Database
    {
        if ($this->database === null) {
            throw new UnexpectedValueException("Database connection it's not established!");
        }
        return $this->database;
    }

    public function onDisable(): void
    {
        if (($this->database !== null)) {
            $this->database->close();
        }
        Inspector::clearCache();
    }
}