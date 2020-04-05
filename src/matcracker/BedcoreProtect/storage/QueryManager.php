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

use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\math\Area;
use matcracker\BedcoreProtect\storage\queries\BlocksQueries;
use matcracker\BedcoreProtect\storage\queries\EntitiesQueries;
use matcracker\BedcoreProtect\storage\queries\InventoriesQueries;
use matcracker\BedcoreProtect\storage\queries\PluginQueries;
use matcracker\BedcoreProtect\storage\queries\QueriesConst;
use matcracker\BedcoreProtect\utils\ConfigParser;
use poggit\libasynql\DataConnector;
use UnexpectedValueException;
use function microtime;
use function preg_match;

final class QueryManager
{
    /** @var DataConnector */
    private $connector;
    /** @var ConfigParser */
    private $configParser;

    /** @var PluginQueries */
    private $pluginQueries;
    /** @var BlocksQueries */
    private $blocksQueries;
    /** @var EntitiesQueries */
    private $entitiesQueries;
    /** @var InventoriesQueries */
    private $inventoriesQueries;

    public function __construct(DataConnector $connector, ConfigParser $configParser)
    {
        $this->connector = $connector;
        $this->configParser = $configParser;

        $this->pluginQueries = new PluginQueries($connector, $configParser);
        $this->entitiesQueries = new EntitiesQueries($connector, $configParser);
        $this->blocksQueries = new BlocksQueries($connector, $configParser, $this->entitiesQueries);
        $this->inventoriesQueries = new InventoriesQueries($connector, $configParser);
    }

    public function init(string $pluginVersion): void
    {
        if (!preg_match('/^(\d+\.)?(\d+\.)?(\*|\d+)$/', $pluginVersion)) {
            throw new UnexpectedValueException("The field {$pluginVersion} must be a version.");
        }

        foreach (QueriesConst::INIT_TABLES as $queryTable) {
            $this->connector->executeGeneric($queryTable);
        }

        $this->connector->executeInsert(QueriesConst::ADD_DATABASE_VERSION, [
            'version' => $pluginVersion
        ]);

        $this->connector->waitAll();

        $this->entitiesQueries->addDefaultEntities();
    }

    public function rollback(Area $area, CommandParser $commandParser): void
    {
        $this->getBlocksQueries()->rollback($area, $commandParser);
        //$this->getInventoriesQueries()->rollback($area, $commandParser);
        //$this->getEntitiesQueries()->rollback($area, $commandParser);
    }

    /**
     * @return BlocksQueries
     */
    public function getBlocksQueries(): BlocksQueries
    {
        return $this->blocksQueries;
    }

    public function restore(Area $area, CommandParser $commandParser): void
    {
        /*$this->getBlocksQueries()->restore($area, $commandParser);
        $this->getInventoriesQueries()->restore($area, $commandParser);
        $this->getEntitiesQueries()->restore($area, $commandParser);*/
    }

    /**
     * @return PluginQueries
     */
    public function getPluginQueries(): PluginQueries
    {
        return $this->pluginQueries;
    }

    /**
     * @param Area $area
     * @param CommandParser $commandParser
     */
    private function rollbackOld(Area $area, CommandParser $commandParser): void
    {
        $startTime = microtime(true);

        $this->getBlocksQueries()->rollback($area, $commandParser);
        $this->getInventoriesQueries()->rollback($area, $commandParser);
        $this->getEntitiesQueries()->rollback($area, $commandParser);

        /*$query = $commandParser->buildLogsSelectionQuery(!$rollback, $area->getBoundingBox());
        $logRows = yield $this->connector->executeSelectRaw($query, [], yield, yield Await::REJECT) => Await::ONCE;

        /** @var int[] $logIds *
        $logIds = [];
        foreach ($logRows as $logRow) {
            $logIds[] = (int)$logRow['log_id'];
        }*/

        /*$touchedChunks = $area->getTouchedChunks($blocks);

        $this->onRollbackComplete($rollback, $area, $commandParser, $startTime, count($touchedChunks), $blocks, $inventories, $entities);*/

    }

    /**
     * @return InventoriesQueries
     */
    public function getInventoriesQueries(): InventoriesQueries
    {
        return $this->inventoriesQueries;
    }

    /**
     * @return EntitiesQueries
     */
    public function getEntitiesQueries(): EntitiesQueries
    {
        return $this->entitiesQueries;
    }


}
