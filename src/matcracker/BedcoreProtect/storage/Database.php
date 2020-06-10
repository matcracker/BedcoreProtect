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

use Generator;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\queries\QueriesConst;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use SOFe\AwaitGenerator\Await;

class Database
{
    /** @var Main */
    protected $plugin;
    /** @var DataConnector */
    private $connector;
    /** @var PatchManager */
    private $patchManager;
    /** @var QueryManager */
    private $queryManager;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Attempts to connect and initialize the database. Returns true if success.
     * @return bool
     */
    final public function connect(): bool
    {
        try {
            $this->connector = libasynql::create($this->plugin, $this->plugin->getConfig()->get('database'), [
                'sqlite' => 'sqlite.sql',
                'mysql' => 'mysql.sql'
            ]);
        } catch (SqlError $error) {
            $this->plugin->getLogger()->critical($this->plugin->getLanguage()->translateString('database.connection.fail'));
            return false;
        }

        $patchResource = $this->plugin->getResource('patches/' . $this->plugin->getParsedConfig()->getDatabaseType() . '_patch.sql');
        if ($patchResource !== null) {
            $this->connector->loadQueryFile($patchResource);
        }
        $this->patchManager = new PatchManager($this->plugin, $this->connector);
        $this->queryManager = new QueryManager($this->plugin, $this->connector);

        return true;
    }

    final public function getQueryManager(): QueryManager
    {
        if (!isset($this->queryManager)) {
            $this->throwDatabaseException();
        }

        return $this->queryManager;
    }

    private function throwDatabaseException(): void
    {
        throw new SqlError(SqlError::STAGE_CONNECT, $this->plugin->getLanguage()->translateString('database.connection.fail'));
    }

    final public function getPatchManager(): PatchManager
    {
        if (!isset($this->patchManager)) {
            $this->throwDatabaseException();
        }

        return $this->patchManager;
    }

    final public function disconnect(): void
    {
        if ($this->connector instanceof DataConnector) {
            $this->connector->waitAll();
            $this->connector->close();
        }
    }

    final public function getStatus(): Generator
    {
        if (!isset($this->connector)) {
            $this->throwDatabaseException();
        }

        $this->connector->executeSelect(QueriesConst::GET_DATABASE_STATUS, [], yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    /**
     * Returns the database version.
     * @return string
     * @internal
     */
    public function getVersion(): string
    {
        if (!isset($this->connector)) {
            $this->throwDatabaseException();
        }

        $version = "";
        $this->connector->executeSelect(
            QueriesConst::GET_DATABASE_STATUS,
            [],
            static function (array $rows) use (&$version) : void {
                $version = (string)$rows[0]['version'];
            }
        );
        $this->connector->waitAll();
        return $version;
    }
}
