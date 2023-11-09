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

use Closure;
use Generator;
use matcracker\BedcoreProtect\commands\CommandData;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\Inspector;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\DataConnectorHelper;
use matcracker\BedcoreProtect\storage\LookupData;
use matcracker\BedcoreProtect\utils\MathUtils;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\CommandSender;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\World\World;
use poggit\libasynql\generic\GenericVariable;
use SOFe\AwaitGenerator\Await;
use function array_map;
use function count;
use function mb_substr;
use function microtime;

/**
 * It contains all the queries methods related to the plugin and logs.
 *
 * Class PluginQueries
 * @package matcracker\BedcoreProtect\storage\queries
 */
class PluginQueries extends Query
{
    public function requestNearLog(Player $inspector, int $radius, int $limit = 4, int $offset = 0): void
    {
        $position = $inspector->getPosition();
        $this->requestLog(
            QueriesConst::GET_NEAR_LOG,
            $inspector,
            $position,
            $position->getWorld()->getFolderName(),
            $radius,
            $limit,
            $offset
        );
    }

    protected function requestLog(string $queryName, CommandSender $sender, Vector3 $position, string $worldName, int $radius, int $limit, int $offset): void
    {
        Await::f2c(function () use ($queryName, $sender, $position, $worldName, $radius, $limit, $offset): Generator {
            /** @var array $rows */
            $rows = yield from $this->requestRawLogByPosition(
                $queryName,
                $position,
                $worldName,
                $radius,
                $limit,
                $offset
            );

            $cmdData = new CommandData([], null, $worldName, $radius, [], [], [], []);

            $this->onRetrieveLogs($queryName, $rows, $sender, $position, $cmdData, $limit, $offset);
        });
    }

    protected function requestRawLogByPosition(string $queryName, Vector3 $position, string $worldName, int $radius, int $limit, int $offset): Generator
    {
        $bb = MathUtils::getRangedVector($position, $radius);
        MathUtils::floorBoundingBox($bb);

        return yield from $this->requestRawAreaLog($queryName, $bb, $worldName, $limit, $offset);
    }

    protected function requestRawAreaLog(string $queryName, AxisAlignedBB $area, string $worldName, int $limit, int $offset): Generator
    {
        return yield from $this->connector->asyncSelect(
            $queryName,
            [
                "min_x" => $area->minX,
                "max_x" => $area->maxX,
                "min_y" => $area->minY,
                "max_y" => $area->maxY,
                "min_z" => $area->minZ,
                "max_z" => $area->maxZ,
                "world_name" => $worldName,
                "limit" => $limit,
                "offset" => $offset
            ]
        );
    }

    protected function onRetrieveLogs(string $queryName, array $rows, CommandSender $sender, Vector3 $position, CommandData $cmdData, int $limit, int $offset): void
    {
        if (count($rows) === 0) {
            $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->plugin->getLanguage()->translateString("subcommand.show.empty-data"));
            return;
        }

        $cacheData = new LookupData($queryName, (int)$rows[0]["cnt_rows"], $sender, $cmdData, $position);
        LookupData::storeData($sender, $cacheData);
        Inspector::sendLogReport($sender, $rows, $limit, $offset);
    }

    public function requestLookup(CommandSender $inspector, CommandData $cmdData, ?Vector3 $position, int $limit = 4, int $offset = 0): void
    {
        Await::f2c(function () use ($inspector, $cmdData, $position, $limit, $offset): Generator {
            $query = "";
            $args = [];

            if (($radius = $cmdData->getRadius()) !== null) {
                $bb = MathUtils::getRangedVector($position, $radius);
            } else {
                $bb = null;
            }

            $this->buildLookupQuery($query, $args, $cmdData, $bb, $limit, $offset);

            $rows = yield from DataConnectorHelper::asyncSelectRaw($this->connector, $query, $args[0]);
            $this->onRetrieveLogs(QueriesConst::DYN_LOOKUP_QUERY, $rows, $inspector, $position, $cmdData, $limit, $offset);
        });
    }

    private function buildLookupQuery(string &$query, array &$args, CommandData $commandData, ?AxisAlignedBB $bb, int $limit = 4, int $offset = 0): void
    {
        $query = /**@lang text */
            "SELECT 
                COUNT(*) OVER () AS cnt_rows, 
                tmp_ids.*, 
                e1.entity_name AS entity_from, e2.entity_name AS entity_to,
                cl.message AS message
            FROM
            (SELECT tmp_logs.*,
                 CASE
                    WHEN tmp_logs.action = 0 OR tmp_logs.action = 6 THEN tmp_logs.new_name
                    WHEN tmp_logs.action = 1 OR tmp_logs.action = 7 THEN tmp_logs.old_name
                    ELSE
                        new_name
                END AS name
                FROM
                (SELECT log_history.*, old_amount, new_amount,
                    CASE
                        WHEN il.old_name IS NULL THEN bl.old_name
                        WHEN bl.old_name IS NULL THEN il.old_name
                    END AS old_name,
                    CASE
                        WHEN il.new_name IS NULL THEN bl.new_name
                        WHEN bl.new_name IS NULL THEN il.new_name
                    END AS new_name
                FROM log_history
                LEFT JOIN blocks_log bl ON log_history.log_id = bl.history_id
                LEFT JOIN inventories_log il ON log_history.log_id = il.history_id
                ) AS tmp_logs
            ) AS tmp_ids
            LEFT JOIN entities_log el ON tmp_ids.log_id = el.history_id
            LEFT JOIN entities e1 ON tmp_ids.who = e1.uuid
            LEFT JOIN entities e2 ON el.entityfrom_uuid = e2.uuid
            LEFT JOIN chat_log cl ON tmp_ids.log_id = cl.history_id WHERE ";

        $variables = [
            "limit" => new GenericVariable("limit", GenericVariable::TYPE_INT, null),
            "offset" => new GenericVariable("offset", GenericVariable::TYPE_INT, null)
        ];
        $parameters = [
            "limit" => $limit,
            "offset" => $offset
        ];

        self::buildConditionalQuery($query, $variables, $parameters, $commandData, $bb);

        $query .= " ORDER BY time DESC LIMIT :limit OFFSET :offset;";

        DataConnectorHelper::asGenericStatement(
            $this->plugin->getParsedConfig()->getDatabaseType(),
            $query,
            $args,
            QueriesConst::DYN_LOOKUP_QUERY,
            $variables,
            $parameters
        );
    }

    /**
     * @param string $query
     * @param GenericVariable[] $variables
     * @param array $params
     * @param CommandData $commandData
     * @param AxisAlignedBB|null $bb
     * @param float|null $currTime
     */
    private static function buildConditionalQuery(string &$query, array &$variables, array &$params, CommandData $commandData, ?AxisAlignedBB $bb, ?float $currTime = null): void
    {
        if (count($users = $commandData->getUsers()) > 0) {
            $variables["users"] = new GenericVariable("users", "list:string", null);
            $params["users"] = $users;

            $query .= "(who IN (SELECT uuid FROM entities WHERE entity_name IN :users)) AND ";
        }

        if (($time = $commandData->getTime()) !== null) {
            $variables["now"] = new GenericVariable("now", GenericVariable::TYPE_FLOAT, null);
            $params["now"] = $currTime ?? microtime(true);
            $variables["time"] = new GenericVariable("time", GenericVariable::TYPE_FLOAT, null);
            $params["time"] = microtime(true) - (float)$time;

            $query .= "(time BETWEEN :time AND :now) AND ";
        }

        if (!$commandData->isGlobalRadius() && $bb !== null) {
            $variables["min_x"] = new GenericVariable("min_x", GenericVariable::TYPE_FLOAT, null);
            $params["min_x"] = $bb->minX;
            $variables["min_y"] = new GenericVariable("min_y", GenericVariable::TYPE_FLOAT, null);
            $params["min_y"] = $bb->minY;
            $variables["min_z"] = new GenericVariable("min_z", GenericVariable::TYPE_FLOAT, null);
            $params["min_z"] = $bb->minZ;

            $variables["max_x"] = new GenericVariable("max_x", GenericVariable::TYPE_FLOAT, null);
            $params["max_x"] = $bb->maxX;
            $variables["max_y"] = new GenericVariable("max_y", GenericVariable::TYPE_FLOAT, null);
            $params["max_y"] = $bb->maxY;
            $variables["max_z"] = new GenericVariable("max_z", GenericVariable::TYPE_FLOAT, null);
            $params["max_z"] = $bb->maxZ;

            $query .= "(x BETWEEN :min_x AND :max_x) AND (y BETWEEN :min_y AND :max_y) AND (z BETWEEN :min_z AND :max_z) AND ";
        }

        $variables["world"] = new GenericVariable("world", GenericVariable::TYPE_STRING, null);
        $params["world"] = $commandData->getWorld();

        $query .= "world_name = :world AND ";

        if (count($actions = $commandData->getActions()) > 0) {
            $variables["actions"] = new GenericVariable("actions", "list:int", null);
            $params["actions"] = array_map(static fn(Action $action): int => $action->value, $actions);

            $query .= "(action IN :actions) AND ";
        }

        if (count($inclusions = $commandData->getInclusions()) > 0) {
            $variables["inclusions"] = new GenericVariable("inclusions", "list:string", null);
            $params["inclusions"] = $inclusions;

            $query .= "(name IN :inclusions) AND ";
        }

        if (count($exclusions = $commandData->getExclusions()) > 0) {
            $variables["exclusions"] = new GenericVariable("exclusions", "list:string", null);
            $params["exclusions"] = $exclusions;

            $query .= "(name NOT IN :exclusions) AND ";
        }

        $query = mb_substr($query, 0, -5); //Remove excessive " AND " string.
    }

    public function requestTransactionLog(Player $inspector, Vector3 $position, string $worldName, int $radius = 0, int $limit = 4, int $offset = 0): void
    {
        $this->requestLog(
            QueriesConst::GET_TRANSACTION_LOG,
            $inspector,
            $position,
            $worldName,
            $radius,
            $limit,
            $offset
        );
    }

    public function requestTransactionLogByPos(Player $inspector, Position $position, int $radius = 0, int $limit = 4, int $offset = 0): void
    {
        $this->requestTransactionLog(
            $inspector,
            $position,
            $position->getWorld()->getFolderName(),
            $radius,
            $limit,
            $offset
        );
    }

    public function requestBlockLog(Player $inspector, Vector3 $position, string $worldName, int $radius = 0, int $limit = 4, int $offset = 0): void
    {
        $this->requestLog(
            QueriesConst::GET_BLOCK_LOG,
            $inspector,
            $position,
            $worldName,
            $radius,
            $limit,
            $offset
        );
    }

    public function requestBlockLogByPos(Player $inspector, Position $position, int $radius = 0, int $limit = 4, int $offset = 0): void
    {
        $this->requestBlockLog(
            $inspector,
            $position,
            $position->getWorld()->getFolderName(),
            $radius,
            $limit,
            $offset
        );
    }

    public function purge(float $time, ?string $worldName, bool $optimize, ?Closure $onSuccess = null): void
    {
        Await::f2c(function () use ($time, $worldName, $optimize, $onSuccess) {
            if ($worldName !== null) {
                /** @var int $affectedRows */
                $affectedRows = yield from $this->connector->asyncChange(QueriesConst::PURGE_WORLD, [
                    "time" => $time,
                    "world_name" => $worldName
                ]);
            } else {
                /** @var int $affectedRows */
                $affectedRows = yield from $this->connector->asyncChange(QueriesConst::PURGE_TIME, ["time" => $time]);
            }

            if ($optimize) {
                yield from $this->connector->asyncGeneric($this->plugin->getParsedConfig()->isSQLite() ? QueriesConst::VACUUM : QueriesConst::OPTIMIZE);
            }

            if ($onSuccess !== null) {
                $onSuccess($affectedRows);
            }
        });
    }

    public function buildLogsSelectionQuery(string &$query, array &$args, CommandData $commandData, ?AxisAlignedBB $bb, ?float $currTime, bool $rollback, int $limit): void
    {
        $variables = [
            "rollback" => new GenericVariable("rollback", GenericVariable::TYPE_BOOL, null),
            "limit" => new GenericVariable("limit", GenericVariable::TYPE_INT, null)
        ];
        $parameters = [
            "rollback" => !$rollback,
            "limit" => $limit
        ];

        if (count($commandData->getInclusions()) > 0 || count($commandData->getExclusions()) > 0) {
            $airName = VanillaBlocks::AIR()->getName();

            $query = /**@lang text */
                "SELECT log_id, name
                FROM 
                (SELECT log_history.*,
                    CASE
                        WHEN old_name = \"$airName\" THEN new_name
                        WHEN new_name = \"$airName\" THEN old_name
                    END AS name
                FROM log_history INNER JOIN blocks_log ON log_history.log_id = history_id
                ) AS tmp_logs
                WHERE rollback = :rollback AND ";
        } else {
            $query = /**@lang text */
                "SELECT log_id FROM log_history WHERE rollback = :rollback AND ";
        }

        self::buildConditionalQuery($query, $variables, $parameters, $commandData, $bb, $currTime);

        $query .= " ORDER BY time" . ($rollback ? " DESC" : "") . " LIMIT :limit;";

        DataConnectorHelper::asGenericStatement(
            $this->plugin->getParsedConfig()->getDatabaseType(),
            $query,
            $args,
            "dyn-log-selection-query",
            $variables,
            $parameters
        );
    }

    public function onRollback(CommandSender $sender, World $world, bool $rollback, array $logIds): Generator
    {
        0 && yield;
    }
}
