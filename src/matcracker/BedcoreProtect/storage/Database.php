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

namespace matcracker\BedcoreProtect\storage;

use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\queries\Queries;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;

class Database
{
    /**@var Main */
    private $plugin;
    /**@var DataConnector */
    private $dataConnector;
    /**@var Queries */
    private $queries;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Attempts to connect to the database. Returns true if success.
     * @return bool
     */
    public final function connect(): bool
    {
        try {
            $this->dataConnector = libasynql::create($this->plugin, $this->plugin->getConfig()->get("database"), [
                "sqlite" => "sqlite.sql",
                "mysql" => "mysql.sql"
            ]);
            $this->queries = new Queries($this->dataConnector, $this->plugin->getParsedConfig());

            return true;
        } catch (SqlError $error) {
            $this->plugin->getLogger()->critical("Could not connect to the database! Check your connection, database settings or plugin configuration file");
        }

        return false;
    }

    public final function getQueries(): Queries
    {
        if (!$this->isConnected()) {
            $this->plugin->getLogger()->critical("Could not connect to the database! Check your connection, database settings or plugin configuration file");
            $this->plugin->getServer()->getPluginManager()->disablePlugin($this->plugin);
        }

        return $this->queries;
    }

    public final function isConnected(): bool
    {
        return isset($this->dataConnector);
    }

    public final function disconnect(): void
    {
        if ($this->isConnected()) {
            $this->dataConnector->waitAll();
            $this->dataConnector->close();
            $this->dataConnector = null;
            $this->queries = null;
        }
    }

}