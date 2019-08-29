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
use matcracker\BedcoreProtect\storage\queries\Queries;
use matcracker\BedcoreProtect\storage\queries\QueriesConst;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use SOFe\AwaitGenerator\Await;

class Database
{
    /**@var Main $plugin */
    private $plugin;
    /**@var DataConnector $connector */
    private $connector;
    /**@var PatchManager $patchManager */
    private $patchManager;
    /**@var Queries $queries */
    private $queries;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Attempts to connect and initialize the database. Returns true if success.
     * @return bool
     */
    public final function connect(): bool
    {
        try {
            $this->connector = libasynql::create($this->plugin, $this->plugin->getConfig()->get('database'), [
                'sqlite' => 'sqlite.sql',
                'mysql' => 'mysql.sql'
            ]);
            $patchResource = $this->plugin->getResource('patches/' . $this->plugin->getParsedConfig()->getDatabaseType() . '_patch.sql');
            if ($patchResource !== null) {
                $this->connector->loadQueryFile($patchResource);
            }
            $this->patchManager = new PatchManager($this->plugin, $this->connector);
            $this->queries = new Queries($this->connector, $this->plugin->getParsedConfig());

            return true;
        } catch (SqlError $error) {
            $this->plugin->getLogger()->critical($this->plugin->getLanguage()->translateString('database.connection.fail'));
        }

        return false;
    }

    public final function getQueries(): Queries
    {
        if (!$this->isConnected() || !isset($this->queries)) {
            $this->plugin->getLogger()->critical($this->plugin->getLanguage()->translateString('database.connection.fail'));
            $this->plugin->getServer()->getPluginManager()->disablePlugin($this->plugin);
        }

        return $this->queries;
    }

    public final function getPatchManager(): PatchManager
    {
        return $this->patchManager;
    }

    public final function isConnected(): bool
    {
        return isset($this->connector);
    }

    public final function disconnect(): void
    {
        if ($this->isConnected()) {
            $this->connector->waitAll();
            $this->connector->close();
            $this->connector = null;
            $this->queries = null;
        }
    }

    public function getStatus(): Generator
    {
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
        $this->connector->executeSelect(QueriesConst::GET_DATABASE_STATUS, [], static function (array $rows) use (&$version) {
            $version = (string)$rows[0]['version'];
        });
        $this->connector->waitAll();
        return $version;
    }

}