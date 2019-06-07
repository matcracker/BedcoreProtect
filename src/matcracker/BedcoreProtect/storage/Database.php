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

namespace matcracker\BedcoreProtect\storage;

use matcracker\BedcoreProtect\Main;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;

class Database
{
    /**@var DataConnector */
    private $database;
    /**@var Queries */
    private $queries;

    public function __construct(Main $plugin)
    {
        $this->database = libasynql::create($plugin, $plugin->getConfig()->get("database"), [
            "sqlite" => "sqlite.sql",
            "mysql" => "mysql.sql"
        ]);
        $this->queries = new Queries($this->database, $plugin->getParsedConfig());
    }

    public function getQueries(): Queries
    {
        return $this->queries;
    }

    public final function close(): void
    {
        if ($this->isConnected()) {
            $this->database->close();
        }
    }

    public final function isConnected(): bool
    {
        return isset($this->database);
    }
}