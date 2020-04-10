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
use matcracker\BedcoreProtect\storage\QueryManager;
use matcracker\BedcoreProtect\utils\ConfigParser;
use pocketmine\level\Position;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Await;
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
                    $commandParser,
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
            },
            static function (): void {
                //NOOP
            }
        );
    }

    /**
     * @param bool $rollback
     * @param Area $area
     * @param CommandParser $commandParser
     * @param int[] $logIds
     * @param float $startTime
     * @param Closure|null $onComplete
     * @return Generator
     */
    abstract protected function onRollback(bool $rollback, Area $area, CommandParser $commandParser, array $logIds, float $startTime, Closure $onComplete): Generator;

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

    final protected function addRawLog(string $uuid, Position $position, Action $action): Generator
    {
        $this->connector->executeInsert(QueriesConst::ADD_HISTORY_LOG, [
            'uuid' => strtolower($uuid),
            'x' => $position->getFloorX(),
            'y' => $position->getFloorY(),
            'z' => $position->getFloorZ(),
            'world_name' => $position->getLevel()->getName(),
            'action' => $action->getType()
        ], yield, yield Await::REJECT);

        return yield Await::ONCE;
    }

    final protected function getLastLogId(): Generator
    {
        return $this->executeSelect(QueriesConst::GET_LAST_LOG_ID);
    }

    final protected function executeSelect(string $query, array $args = []): Generator
    {
        $this->connector->executeSelect($query, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }
}
