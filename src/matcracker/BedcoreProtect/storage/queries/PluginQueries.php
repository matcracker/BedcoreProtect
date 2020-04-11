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
use matcracker\BedcoreProtect\Inspector;
use matcracker\BedcoreProtect\math\Area;
use pocketmine\block\Block;
use pocketmine\command\CommandSender;
use pocketmine\level\Position;
use pocketmine\Player;
use SOFe\AwaitGenerator\Await;

/**
 * It contains all the queries methods related to the plugin and logs.
 *
 * Class PluginQueries
 * @package matcracker\BedcoreProtect\storage\queries
 */
class PluginQueries extends Query
{

    /**
     * Can be used only with SQLite.
     */
    final public function beginTransaction(): void
    {
        if ($this->configParser->isSQLite()) {
            $this->connector->executeGeneric(QueriesConst::BEGIN_TRANSACTION);
        }
    }

    public function requestNearLog(Player $inspector, Position $position, int $near): void
    {
        $this->requestLog(QueriesConst::GET_NEAR_LOG, $inspector, $position, $near);
    }

    /**
     * @param string $queryName
     * @param Player $inspector
     * @param Position $position
     * @param int $near
     */
    private function requestLog(string $queryName, Player $inspector, Position $position, int $near = 0): void
    {
        $minV = $position->subtract($near, $near, $near)->floor();
        $maxV = $position->add($near, $near, $near)->floor();

        $this->connector->executeSelect($queryName, [
            'min_x' => $minV->getX(),
            'max_x' => $maxV->getX(),
            'min_y' => $minV->getY(),
            'max_y' => $maxV->getY(),
            'min_z' => $minV->getZ(),
            'max_z' => $maxV->getZ(),
            'world_name' => $position->getLevel()->getName()
        ], static function (array $rows) use ($inspector): void {
            Inspector::cacheLogs($inspector, $rows);
            Inspector::parseLogs($inspector, $rows);
        });
    }

    public function requestLookup(CommandSender $sender, CommandParser $parser): void
    {
        $query = $parser->buildLookupQuery();
        $this->connector->executeSelectRaw(
            $query,
            [],
            static function (array $rows) use ($sender): void {
                Inspector::cacheLogs($sender, $rows);
                Inspector::parseLogs($sender, $rows);
            }
        );
    }

    public function requestTransactionLog(Player $inspector, Position $position): void
    {
        $this->requestLog(QueriesConst::GET_TRANSACTION_LOG, $inspector, $position);
    }

    public function requestBlockLog(Player $inspector, Block $block): void
    {
        $this->requestLog(QueriesConst::GET_BLOCK_LOG, $inspector, $block->asPosition());
    }

    public function purge(int $time, ?callable $onSuccess = null): void
    {
        $this->connector->executeChange(QueriesConst::PURGE, [
            'time' => $time
        ], $onSuccess);
    }

    /**
     * Can be used only with SQLite.
     */
    final public function endTransaction(): void
    {
        if ($this->configParser->isSQLite()) {
            $this->connector->executeGeneric(QueriesConst::END_TRANSACTION);
        }
    }

    /**
     * Can be used only with SQLite.
     * It ends the current transaction and starts a new one.
     */
    final public function storeTransaction(): void
    {
        Await::f2c(
            function (): Generator {
                if ($this->configParser->isSQLite()) {
                    yield $this->executeGeneric(QueriesConst::END_TRANSACTION);
                    yield $this->executeGeneric(QueriesConst::BEGIN_TRANSACTION);
                }
            },
            static function (): void {
                //NOOP
            }
        );
    }

    protected function onRollback(bool $rollback, Area $area, CommandParser $commandParser, array $logIds, float $startTime, Closure $onComplete): Generator
    {
        yield from [];
    }
}
