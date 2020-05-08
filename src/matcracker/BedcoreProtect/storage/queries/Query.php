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

namespace matcracker\BedcoreProtect\storage\queries;

use Closure;
use Generator;
use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\math\Area;
use matcracker\BedcoreProtect\serializable\SerializablePosition;
use matcracker\BedcoreProtect\storage\QueryManager;
use matcracker\BedcoreProtect\utils\ConfigParser;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Await;
use function floor;
use function microtime;
use function strtolower;

abstract class Query
{
    /** @var DataConnector */
    protected $connector;
    /** @var ConfigParser */
    protected $configParser;

    public function __construct(DataConnector $connector, ConfigParser $configParser)
    {
        $this->connector = $connector;
        $this->configParser = $configParser;
    }

    public function rollback(Area $area, CommandParser $commandParser, array $logIds, ?Closure $onPreComplete = null, bool $isLastRollback = true): void
    {
        $this->rawRollback(true, $area, $commandParser, $logIds, $onPreComplete, $isLastRollback);
    }

    /**
     * @param bool $rollback
     * @param Area $area
     * @param CommandParser $commandParser
     * @param int[] $logIds
     * @param Closure|null $onPreComplete
     * @param bool $isLastRollback
     */
    public function rawRollback(bool $rollback, Area $area, CommandParser $commandParser, array $logIds, ?Closure $onPreComplete = null, bool $isLastRollback = true): void
    {
        Await::f2c(
            function () use ($rollback, $area, $commandParser, $logIds, $onPreComplete, $isLastRollback) : Generator {
                yield $this->onRollback(
                    $rollback,
                    $area,
                    $logIds,
                    microtime(true),
                    function () use ($rollback, $area, $commandParser, $logIds, $onPreComplete, $isLastRollback): void {
                        if ($onPreComplete) {
                            $onPreComplete();
                        }

                        if ($isLastRollback) {
                            $this->updateRollbackStatus($rollback, $logIds);
                            QueryManager::sendRollbackReport($rollback, $area, $commandParser);
                        }
                    }
                );
            }
        );
    }

    /**
     * @param bool $rollback
     * @param Area $area
     * @param int[] $logIds
     * @param float $startTime
     * @param Closure $onComplete
     * @return Generator
     */
    abstract protected function onRollback(bool $rollback, Area $area, array $logIds, float $startTime, Closure $onComplete): Generator;

    /**
     * @param bool $rollback
     * @param int[] $logIds
     */
    final protected function updateRollbackStatus(bool $rollback, array $logIds): void
    {
        $this->connector->executeChange(QueriesConst::UPDATE_ROLLBACK_STATUS, [
            'rollback' => $rollback,
            'log_ids' => $logIds
        ]);
    }

    public function restore(Area $area, CommandParser $commandParser, array $logIds, ?Closure $onPreComplete = null, bool $isLastRollback = true): void
    {
        $this->rawRollback(false, $area, $commandParser, $logIds, $onPreComplete, $isLastRollback);
    }

    final protected function executeGeneric(string $query, array $args = []): Generator
    {
        $this->connector->executeGeneric($query, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    /**
     * @param string $query
     * @param array $args
     * @param bool $multiParams if true, returns all parameters of callable 'onInserted(int $insertId, int $affectedRows)' instead of only $insertId.
     * @return Generator
     */
    final protected function executeInsertRaw(string $query, array $args = [], bool $multiParams = false): Generator
    {
        $this->connector->executeInsertRaw($query, $args, yield ($multiParams ? Await::RESOLVE_MULTI : Await::RESOLVE), yield Await::REJECT);
        return yield Await::ONCE;
    }

    final protected function addRawLog(string $uuid, SerializablePosition $position, Action $action): Generator
    {
        return $this->executeInsert(QueriesConst::ADD_HISTORY_LOG, [
            'uuid' => strtolower($uuid),
            'x' => (int)floor($position->getX()),
            'y' => (int)floor($position->getY()),
            'z' => (int)floor($position->getZ()),
            'world_name' => $position->getWorldName(),
            'action' => $action->getType()
        ]);
    }

    final protected function executeInsert(string $query, array $args = []): Generator
    {
        $this->connector->executeInsert($query, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    final protected function executeSelect(string $query, array $args = []): Generator
    {
        $this->connector->executeSelect($query, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }
}
