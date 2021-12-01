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

namespace matcracker\BedcoreProtect\storage\queries;

use Generator;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\utils\AwaitMutex;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\World\World;
use poggit\libasynql\DataConnector;
use function mb_strtolower;

abstract class Query
{
    use DefaultQueriesTrait {
        __construct as DefQueriesConstr;
    }

    private static AwaitMutex $mutex;

    public function __construct(protected Main $plugin, DataConnector $connector)
    {
        $this->DefQueriesConstr($connector);
    }

    final protected static function getMutex(): AwaitMutex
    {
        if (!isset(self::$mutex)) {
            self::$mutex = new AwaitMutex();
        }

        return self::$mutex;
    }

    /**
     * @param CommandSender $sender
     * @param World $world
     * @param bool $rollback
     * @param int[] $logIds
     * @return Generator
     */
    abstract public function onRollback(CommandSender $sender, World $world, bool $rollback, array $logIds): Generator;

    final protected function addRawLog(string $uuid, Vector3 $position, string $worldName, Action $action, float $time): Generator
    {
        return $this->executeInsert(QueriesConst::ADD_HISTORY_LOG, [
            "uuid" => mb_strtolower($uuid),
            "x" => $position->getX(),
            "y" => $position->getY(),
            "z" => $position->getZ(),
            "world_name" => $worldName,
            "action" => $action->getType(),
            "time" => $time
        ]);
    }
}
