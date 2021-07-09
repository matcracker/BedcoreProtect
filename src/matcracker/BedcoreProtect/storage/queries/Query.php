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

use Closure;
use Generator;
use matcracker\BedcoreProtect\commands\CommandData;
use matcracker\BedcoreProtect\config\ConfigParser;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\QueryManager;
use pocketmine\command\CommandSender;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Await;
use function mb_strtolower;

abstract class Query
{
    use DefaultQueriesTrait {
        __construct as DefQueriesConstr;
    }

    protected Main $plugin;
    protected ConfigParser $configParser;

    public function __construct(Main $plugin, DataConnector $connector)
    {
        $this->DefQueriesConstr($connector);
        $this->plugin = $plugin;
        $this->configParser = $plugin->getParsedConfig();
    }

    public function rollback(CommandSender $sender, CommandData $cmdData, Level $world, array $logIds, ?Closure $onPreComplete = null, bool $isLastRollback = true): void
    {
        $this->rawRollback($sender, $cmdData, $world, true, $logIds, $onPreComplete, $isLastRollback);
    }

    /**
     * @param CommandSender $sender
     * @param CommandData $cmdData
     * @param Level $world
     * @param bool $rollback
     * @param int[] $logIds
     * @param Closure|null $onPreComplete
     * @param bool $isLastRollback
     */
    public function rawRollback(CommandSender $sender, CommandData $cmdData, Level $world, bool $rollback, array $logIds, ?Closure $onPreComplete = null, bool $isLastRollback = true): void
    {
        $senderName = $sender->getName();
        $worldName = $world->getFolderName();

        Await::g2c(
            $this->onRollback(
                $sender,
                $world,
                $rollback,
                $logIds,
                function () use ($senderName, $cmdData, $worldName, $rollback, $logIds, $onPreComplete, $isLastRollback): void {
                    if ($onPreComplete) {
                        $onPreComplete();
                    }

                    if ($isLastRollback) {
                        $this->updateRollbackStatus($rollback, $logIds);
                        QueryManager::sendRollbackReport($senderName, $cmdData, $worldName, $rollback);
                    }
                }
            )
        );
    }

    /**
     * @param CommandSender $sender
     * @param Level $world
     * @param int[] $logIds
     * @param bool $rollback
     * @param Closure $onComplete
     * @return Generator
     */
    abstract protected function onRollback(CommandSender $sender, Level $world, bool $rollback, array $logIds, Closure $onComplete): Generator;

    /**
     * @param bool $rollback
     * @param int[] $logIds
     */
    final protected function updateRollbackStatus(bool $rollback, array $logIds): void
    {
        $this->connector->executeChange(QueriesConst::UPDATE_ROLLBACK_STATUS, [
            "rollback" => $rollback,
            "log_ids" => $logIds
        ]);
    }

    public function restore(CommandSender $sender, CommandData $cmdData, Level $world, array $logIds, ?Closure $onPreComplete = null, bool $isLastRollback = true): void
    {
        $this->rawRollback($sender, $cmdData, $world, false, $logIds, $onPreComplete, $isLastRollback);
    }

    final protected function addRawLog(string $uuid, Vector3 $position, string $worldName, Action $action, float $time): Generator
    {
        return $this->executeInsert(QueriesConst::ADD_HISTORY_LOG, [
            "uuid" => mb_strtolower($uuid),
            "x" => $position->getFloorX(),
            "y" => $position->getFloorY(),
            "z" => $position->getFloorZ(),
            "world_name" => $worldName,
            "action" => $action->getType(),
            "time" => $time
        ]);
    }
}
