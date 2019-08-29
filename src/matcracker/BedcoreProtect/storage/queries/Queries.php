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

use Generator;
use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\Inspector;
use matcracker\BedcoreProtect\math\Area;
use matcracker\BedcoreProtect\utils\ConfigParser;
use pocketmine\block\Block;
use pocketmine\command\CommandSender;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\Server;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Await;
use UnexpectedValueException;

class Queries
{
    use QueriesBlocksTrait, QueriesInventoriesTrait, QueriesEntitiesTrait;

    /**@var DataConnector $connector */
    protected $connector;
    /**@var ConfigParser $configParser */
    protected $configParser;

    public function __construct(DataConnector $connector, ConfigParser $configParser)
    {
        $this->connector = $connector;
        $this->configParser = $configParser;
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

        $this->addDefaultEntities();
    }

    private function addDefaultEntities(): void
    {
        $serverUuid = Server::getInstance()->getServerUniqueId()->toString();
        $this->addRawEntity($serverUuid, '#console');
        $this->addRawEntity('flow-uuid', '#flow');
        $this->addRawEntity('water-uuid', '#water');
        $this->addRawEntity('still water-uuid', '#water');
        $this->addRawEntity('lava-uuid', '#lava');
        $this->addRawEntity('fire block-uuid', '#fire');
        $this->addRawEntity('leaves-uuid', '#decay');
    }

    /**
     * Can be used only with SQLite
     */
    public final function beginTransaction(): void
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
     * @param int|null $near
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
        $this->connector->executeSelectRaw($query, [], static function (array $rows) use ($sender): void {
            Inspector::cacheLogs($sender, $rows);
            Inspector::parseLogs($sender, $rows);
        });
    }

    public function rollback(Area $area, CommandParser $commandParser): void
    {
        $this->startRollback(true, $area, $commandParser);
    }

    public function restore(Area $area, CommandParser $commandParser): void
    {
        $this->startRollback(false, $area, $commandParser);
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
     * Can be used only with SQLite
     */
    public final function endTransaction(): void
    {
        if ($this->configParser->isSQLite()) {
            $this->connector->executeGeneric(QueriesConst::END_TRANSACTION);
        }
    }

    /**
     * @param bool $rollback
     * @param int[] $logIds
     * @internal
     */
    public final function updateRollbackStatus(bool $rollback, array $logIds): void
    {
        $this->connector->executeChange(QueriesConst::UPDATE_ROLLBACK_STATUS, [
            'rollback' => $rollback,
            'log_ids' => $logIds
        ]);
    }

    private function addRawLog(string $uuid, Position $position, Action $action): void
    {
        $this->connector->executeInsert(QueriesConst::ADD_HISTORY_LOG, [
            'uuid' => strtolower($uuid),
            'x' => $position->getFloorX(),
            'y' => $position->getFloorY(),
            'z' => $position->getFloorZ(),
            'world_name' => $position->getLevel()->getName(),
            'action' => $action->getType()
        ]);
    }

    private function getLastLogId(): Generator
    {
        $this->connector->executeSelect(QueriesConst::GET_LAST_LOG_ID, [], yield, yield Await::REJECT);
        return yield Await::ONCE;
    }
}