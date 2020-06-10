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
use pocketmine\plugin\PluginException;

/**
 * It contains all the queries methods related to the plugin and logs.
 *
 * Class PluginQueries
 * @package matcracker\BedcoreProtect\storage\queries
 */
class PluginQueries extends Query
{
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
            'world_name' => $position->getLevelNonNull()->getName()
        ], static function (array $rows) use ($inspector): void {
            Inspector::saveLogs($inspector, $rows);
            Inspector::parseLogs($inspector, $rows);
        });
    }

    public function requestLookup(CommandSender $sender, CommandParser $parser): void
    {
        $this->connector->executeSelectRaw(
            $parser->buildLookupQuery(),
            [],
            static function (array $rows) use ($sender): void {
                Inspector::saveLogs($sender, $rows);
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

    protected function onRollback(bool $rollback, Area $area, CommandParser $commandParser, array $logIds, Closure $onComplete): Generator
    {
        throw new PluginException("\"onRollback()\" method is not available for " . self::class);
    }
}
