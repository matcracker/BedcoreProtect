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

namespace matcracker\BedcoreProtect\storage;

use Generator;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\queries\DefaultQueriesTrait;
use matcracker\BedcoreProtect\storage\queries\QueriesConst;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;

final class DatabaseManager
{
    use DefaultQueriesTrait {
        __construct as DefQueriesConstr;
    }

    private PatchManager $patchManager;
    private QueryManager $queryManager;

    public function __construct(protected Main $plugin)
    {
    }

    /**
     * Attempts to connect and initialize the database. Returns true if success.
     * @return bool
     */
    public function connect(): bool
    {
        try {
            $this->connector = libasynql::create(
                $this->plugin,
                $this->plugin->getConfig()->get("database"),
                [
                    "sqlite" => "sqlite.sql",
                    "mysql" => "mysql.sql"
                ],
                $this->plugin->getParsedConfig()->getDebugMode()
            );
        } catch (SqlError) {
            $this->plugin->getLogger()->critical($this->plugin->getLanguage()->translateString("database.connection.fail"));
            return false;
        }

        $this->DefQueriesConstr($this->connector);

        $patchResource = $this->plugin->getResource("patches/" . $this->plugin->getParsedConfig()->getDatabaseType() . "_patch.sql");
        if ($patchResource !== null) {
            $this->connector->loadQueryFile($patchResource);
        }
        $this->patchManager = new PatchManager($this->plugin, $this->connector);
        $this->queryManager = new QueryManager($this->plugin, $this->connector);

        return true;
    }

    public function reloadConfiguration(): void
    {
        $this->connector->setLoggingQueries($this->plugin->getParsedConfig()->getDebugMode());
    }

    public function getQueryManager(): QueryManager
    {
        return $this->queryManager;
    }

    public function getPatchManager(): PatchManager
    {
        return $this->patchManager;
    }

    public function disconnect(): void
    {
        if (!isset($this->connector)) {
            return;
        }

        //Wait to execute all the queued queries.
        $this->connector->waitAll();

        if ($this->plugin->getParsedConfig()->isSQLite()) {
            /*
             * According to SQLite documentation (https://www.sqlite.org/pragma.html#pragma_optimize)
             * it is recommended to run optimization just before closing the database or every few hours/days.
             */
            $this->optimize();
            $this->connector->waitAll();
        }

        $this->connector->close();
    }

    public function optimize(): void
    {
        if ($this->plugin->getParsedConfig()->isSQLite()) {
            $this->connector->executeGeneric(QueriesConst::OPTIMIZE);
        }
    }

    public function getStatus(): Generator
    {
        return $this->executeSelect(QueriesConst::GET_DATABASE_STATUS);
    }

    /**
     * Returns the database version.
     * @return string
     * @internal
     */
    public function getVersion(): string
    {
        $version = "";

        $this->connector->executeSelect(
            QueriesConst::GET_DATABASE_STATUS,
            [],
            static function (array $rows) use (&$version): void {
                $version = (string)$rows[0]["version"];
            }
        );
        $this->connector->waitAll();
        return $version;
    }
}
