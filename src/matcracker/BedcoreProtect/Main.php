<?php

/*
 * BedcoreProtect
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

    /**@var Database */
    private $database;

    public function onEnable(): void //TODO: Check extensions
    {
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