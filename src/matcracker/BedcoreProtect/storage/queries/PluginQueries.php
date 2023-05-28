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
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\Inspector;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\LookupData;
use matcracker\BedcoreProtect\utils\MathUtils;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\CommandSender;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\World\Position;
use pocketmine\World\World;
use poggit\libasynql\generic\GenericStatementImpl;
use poggit\libasynql\generic\GenericVariable;
use poggit\libasynql\result\SqlSelectResult;
use poggit\libasynql\SqlDialect;
use poggit\libasynql\SqlThread;
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
        $this->requestLog(
            QueriesConst::GET_NEAR_LOG,
            LookupData::NEAR_LOG,
            $inspector,
            $inspector->getPosition(),
            new CommandData(null, null, $inspector->getWorld()->getFolderName(), $radius),
            $limit,
            $offset
        );
    }

    private function requestLog(string $queryName, int $queryType, CommandSender $inspector, Position $position, CommandData $cmdData, int $limit = 4, int $offset = 0): void
    {
        $bb = MathUtils::getRangedVector($position, $cmdData->getRadius() ?? 0);
        MathUtils::floorBoundingBox($bb);

        $this->connector->executeSelect(
            $queryName,
            [
                "min_x" => $bb->minX,
                "max_x" => $bb->maxX,
                "min_y" => $bb->minY,
                "max_y" => $bb->maxY,
                "min_z" => $bb->minZ,
                "max_z" => $bb->maxZ,
                "world_name" => $position->getWorld()->getFolderName(),
                "limit" => $limit,
                "offset" => $offset
            ],
            $this->onSuccessLog($queryType, $inspector, $position, $cmdData, $limit, $offset)
        );
    }

    private function onSuccessLog(int $queryType, CommandSender $inspector, ?Position $position, CommandData $cmdData, int $limit, int $offset): callable
    {
        return function (array $rows) use ($queryType, $inspector, $cmdData, $position, $limit, $offset): void {
            if (count($rows) === 0) {
                $inspector->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->plugin->getLanguage()->translateString("subcommand.show.empty-data"));

                return;
            }

            LookupData::storeData($inspector, new LookupData($queryType, (int)$rows[0]["cnt_rows"], $inspector, $cmdData, $position));
            Inspector::sendLogReport($inspector, $rows, $limit, $offset);
        };
    }

    public function requestLookup(CommandSender $inspector, CommandData $cmdData, ?Position $position, int $limit = 4, int $offset = 0): void
    {
        $query = "";
        $args = [[]];

        if (($radius = $cmdData->getRadius()) !== null) {
            $bb = MathUtils::getRangedVector($position, $radius);
        } else {
            $bb = null;
        }

        $this->buildLookupQuery($query, $args, $cmdData, $bb, $limit, $offset);

        $this->connector->executeImplRaw(
            [$query],
            $args,
            [SqlThread::MODE_SELECT],
            /** @var SqlSelectResult[] $results */
            function (array $results) use ($inspector, $position, $cmdData, $limit, $offset): void {
                $result = $results[count($results) - 1];
                $this->onSuccessLog(LookupData::LOOKUP_LOG, $inspector, $position, $cmdData, $limit, $offset)($result->getRows());
            },
            null
        );
    }

    private function buildLookupQuery(string &$query, array &$args, CommandData $commandData, ?AxisAlignedBB $bb, int $limit = 4, int $offset = 0): void
    {
        $query = /**@lang text */
            "SELECT COUNT(*) OVER () AS cnt_rows, tmp_ids.*, e1.entity_name AS entity_from, e2.entity_name AS entity_to                
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
            LEFT JOIN entities e2 ON el.entityfrom_uuid = e2.uuid WHERE ";

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

        $this->asGenericStatement($query, $args, "dyn-lookup-query", $variables, $parameters);
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
        if (($users = $commandData->getUsers()) !== null) {
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

        if (($actions = $commandData->getActions()) !== null) {
            $variables["actions"] = new GenericVariable("actions", "list:int", null);
            $params["actions"] = array_map(static function (Action $action): int {
                return $action->getType();
            }, $actions);

            $query .= "(action IN :actions) AND ";
        }

        if (($inclusions = $commandData->getInclusions()) !== null) {
            $variables["inclusions"] = new GenericVariable("inclusions", "list:string", null);
            $params["inclusions"] = $inclusions;

            $query .= "(name IN :inclusions) AND ";
        }

        if (($exclusions = $commandData->getExclusions()) !== null) {
            $variables["exclusions"] = new GenericVariable("exclusions", "list:string", null);
            $params["exclusions"] = $exclusions;

            $query .= "(name NOT IN :exclusions) AND ";
        }

        $query = mb_substr($query, 0, -5); //Remove excessive " AND " string.
    }

    /**
     * @param string $query
     * @param array[] $args
     * @param string $statementName
     * @param GenericVariable[] $variables
     * @param array $parameters
     */
    private function asGenericStatement(string &$query, array &$args, string $statementName, array $variables, array $parameters): void
    {
        $isSQLite = $this->plugin->getParsedConfig()->isSQLite();

        $statement = GenericStatementImpl::forDialect(
            $isSQLite ? SqlDialect::SQLITE : SqlDialect::MYSQL,
            $statementName,
            [$query],
            "",
            $variables,
            null,
            0
        );

        [$query] = $statement->format($parameters, $isSQLite ? "" : "?", $args);
    }

    public function requestTransactionLog(Player $inspector, Position $position, int $radius = 0, int $limit = 4, int $offset = 0): void
    {
        $this->requestLog(
            QueriesConst::GET_TRANSACTION_LOG,
            LookupData::TRANSACTION_LOG,
            $inspector,
            $position,
            new CommandData(null, null, $position->getWorld()->getFolderName(), $radius),
            $limit,
            $offset
        );
    }

    public function requestBlockLog(Player $inspector, Position $blockPos, int $radius = 0, int $limit = 4, int $offset = 0): void
    {
        $this->requestLog(
            QueriesConst::GET_BLOCK_LOG,
            LookupData::BLOCK_LOG,
            $inspector,
            $blockPos,
            new CommandData(null, null, $blockPos->getWorld()->getFolderName(), $radius),
            $limit,
            $offset
        );
    }

    public function purge(float $time, ?string $worldName, bool $optimize, ?Closure $onSuccess = null): void
    {
        Await::f2c(
            function () use ($time, $worldName, $optimize, $onSuccess) {
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
            }
        );
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

        if ($commandData->getInclusions() !== null || $commandData->getExclusions() !== null) {
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

        $this->asGenericStatement($query, $args, "dyn-log-selection-query", $variables, $parameters);
    }

    public function onRollback(CommandSender $sender, World $world, bool $rollback, array $logIds): Generator
    {
        0 && yield;
    }
}
