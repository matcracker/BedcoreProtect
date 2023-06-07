<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
 *
 * Copyright (C) 2019-2023
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
use matcracker\BedcoreProtect\enums\ActionType;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\utils\AwaitMutex;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\World\World;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Mutex;
use function mb_strtolower;

abstract class Query
{
    private static Mutex $mutex;

    public function __construct(protected Main $plugin, protected DataConnector $connector)
    {
    }

    final protected static function getMutex(): Mutex
    {
        return self::$mutex ??= new Mutex();
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
        return yield from $this->connector->asyncInsert(QueriesConst::ADD_HISTORY_LOG, [
            "uuid" => mb_strtolower($uuid),
            "x" => $position->getX(),
            "y" => $position->getY(),
            "z" => $position->getZ(),
            "world_name" => $worldName,
            "action" => $action->getId(),
            "time" => $time
        ]);
    }
}
