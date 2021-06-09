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

namespace matcracker\BedcoreProtect\commands;

use BadMethodCallException;
use InvalidArgumentException;
use matcracker\BedcoreProtect\config\ConfigParser;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\utils\MathUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\math\AxisAlignedBB;
use pocketmine\Server;
use poggit\libasynql\generic\GenericStatementImpl;
use poggit\libasynql\generic\GenericVariable;
use poggit\libasynql\SqlDialect;
use UnexpectedValueException;
use function array_filter;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_shift;
use function array_unique;
use function count;
use function ctype_digit;
use function explode;
use function implode;
use function intval;
use function mb_strtolower;
use function mb_substr;
use function microtime;

final class CommandParser
{
    public const MAX_PARAMETERS = 6;
    public const PARAMETERS = [
        "user", "time", "radius", "action", "include", "exclude"
    ];

    /** @var Action[][] */
    public static $ACTIONS;

    /** @var string */
    private $senderName;
    /** @var ConfigParser */
    private $configParser;
    /** @var string[] */
    private $arguments;
    /** @var string[] */
    private $requiredParams;
    /** @var bool */
    private $parsed = false;

    /** @var string */
    private $errorMessage;

    //Default data values
    /** @var array */
    private $data = [
        "user" => null,
        "time" => null,
        "radius" => null,
        "action" => null,
        "inclusions" => null,
        "exclusions" => null
    ];

    /**
     * CommandParser constructor.
     * @param string $senderName
     * @param ConfigParser $configParser
     * @param string[] $arguments
     * @param string[] $requiredParams
     * @param bool $shift It shift the first element of array used internally for command arguments. Default false.
     */
    public function __construct(string $senderName, ConfigParser $configParser, array $arguments, array $requiredParams = [], bool $shift = false)
    {
        $this->senderName = $senderName;
        $this->configParser = $configParser;
        $this->arguments = $arguments;
        $this->requiredParams = $requiredParams;
        if ($shift) {
            array_shift($this->arguments);
        }
    }

    /**
     * @internal
     */
    final public static function initActions(): void
    {
        if (self::$ACTIONS === null) {
            self::$ACTIONS = [
                "block" => [Action::PLACE(), Action::BREAK(), Action::SPAWN(), Action::DESPAWN()],
                "+block" => [Action::PLACE()],
                "-block" => [Action::BREAK()],
                "click" => [Action::CLICK()],
                "container" => [Action::ADD(), Action::REMOVE()],
                "+container" => [Action::ADD()],
                "-container" => [Action::REMOVE()],
                "kill" => [Action::KILL()]
            ];
        }
    }

    public function parse(): bool
    {
        $lang = Main::getInstance()->getLanguage();

        if (($c = count($this->arguments)) < 1 || $c > self::MAX_PARAMETERS) {
            $this->errorMessage = $lang->translateString("parser.few-many-parameters", [self::MAX_PARAMETERS]);

            return false;
        }

        foreach ($this->arguments as $argument) {
            $arrayData = explode("=", $argument);
            if (count($arrayData) !== 2) {
                $this->errorMessage = $lang->translateString("parser.invalid-parameter", [implode(", ", self::PARAMETERS)]);
                return false;
            }

            $param = mb_strtolower($arrayData[0]);
            $paramValues = (string)$arrayData[1];

            switch ($param) {
                case "users":
                case "user":
                case "u":
                    $users = explode(",", $paramValues);

                    foreach ($users as $user) {
                        if (mb_substr($user, 0, 1) !== "#") { //Entity
                            if (!Server::getInstance()->getOfflinePlayer($user)->hasPlayedBefore()) {
                                $this->errorMessage = $lang->translateString("parser.no-player", [$user]);

                                return false;
                            }
                        }
                    }
                    $this->data["user"] = array_unique($users);
                    break;
                case "time":
                case "t":
                    $time = Utils::parseTime($paramValues);
                    if ($time === 0) {
                        $this->errorMessage = $lang->translateString("parser.invalid-amount-time");

                        return false;
                    }
                    $this->data["time"] = $time;
                    break;
                case "radius":
                case "r":
                    if (!ctype_digit($paramValues)) {
                        $this->errorMessage = $lang->translateString("parser.invalid-amount-radius");

                        return false;
                    }
                    $paramValues = (int)$paramValues;
                    $maxRadius = $this->configParser->getMaxRadius();
                    if ($paramValues < 0 || ($maxRadius !== 0 && $paramValues > $maxRadius)) {
                        $this->errorMessage = $lang->translateString("parser.invalid-radius");

                        return false;
                    }

                    $this->data["radius"] = $paramValues;
                    break;
                case "action":
                case "a":
                    $paramValues = mb_strtolower($paramValues);
                    if (!array_key_exists($paramValues, self::$ACTIONS)) {
                        $this->errorMessage = $lang->translateString("parser.invalid-action", [$paramValues, implode(", ", array_keys(self::$ACTIONS))]);

                        return false;
                    }

                    $this->data["action"] = self::$ACTIONS[$paramValues];
                    break;
                case "include":
                case "i":
                case "exclude":
                case "e":
                    $index = mb_substr($param, 0, 1) === "i" ? "inclusions" : "exclusions";

                    /** @var int[] $itemIds */
                    $itemIds = [];
                    /** @var int[] $itemMetas */
                    $itemMetas = [];

                    foreach (explode(",", $paramValues) as $strItem) {
                        try {
                            /** @var Item $item */
                            $item = ItemFactory::fromString($strItem);
                        } catch (InvalidArgumentException $exception) {
                            $this->errorMessage = $lang->translateString("parser.invalid-block-" . ($index === "inclusions" ? "include" : "exclude"), [$strItem]);

                            return false;
                        }

                        $itemIds[] = $item->getId();
                        $itemMetas[] = $item->getDamage();
                    }

                    $this->data[$index] = [
                        "ids" => $itemIds,
                        "metas" => $itemMetas
                    ];

                    break;
                default:
                    $this->errorMessage = $lang->translateString("parser.invalid-parameter", [implode(", ", self::PARAMETERS)]);
                    return false;
            }
        }
        $filter = array_filter($this->data, static function ($value): bool {
            return $value !== null;
        });

        if (count($filter) === 0) {
            return false;
        }

        if (count(array_intersect_key(array_flip($this->requiredParams), $filter)) !== count($this->requiredParams)) {
            $this->errorMessage = $lang->translateString("parser.missing-parameters", [implode(",", $this->requiredParams)]);

            return false;
        }

        $this->parsed = true;

        return true;
    }

    public function buildLogsSelectionQuery(string &$query, array &$args, bool $rollback, AxisAlignedBB $bb): void
    {
        if (!$this->parsed) {
            throw new BadMethodCallException("Before invoking this method, you need to invoke CommandParser::parse()");
        }

        $variables = [
            "rollback" => new GenericVariable("rollback", GenericVariable::TYPE_INT, null)
        ];
        $parameters = [
            "rollback" => intval(!$rollback)
        ];

        if ($this->getInclusions() !== null || $this->getExclusions() !== null) {
            $query = /**@lang text */
                "SELECT log_id, id, meta 
                FROM 
                (SELECT log_history.*,
                    CASE
                        WHEN old_id = 0 THEN new_id
                        WHEN new_id = 0 THEN old_id
                    END AS id,
                    CASE
                        WHEN old_id = 0 THEN new_meta
                        WHEN new_id = 0 THEN old_meta
                    END AS meta
                FROM log_history INNER JOIN blocks_log ON log_history.log_id = history_id
                ) AS tmp_logs
                WHERE rollback = :rollback AND ";
        } else {
            $query = /**@lang text */
                "SELECT log_id FROM log_history WHERE rollback = :rollback AND ";
        }

        $this->buildConditionalQuery($query, $variables, $parameters, $bb);

        $query .= " ORDER BY time" . ($rollback ? " DESC" : "") . ";";

        $statement = GenericStatementImpl::forDialect(
            $this->configParser->isSQLite() ? SqlDialect::SQLITE : SqlDialect::MYSQL,
            "dyn-log-selection-query",
            $query,
            "",
            $variables,
            null,
            0
        );

        $query = $statement->format($parameters, $this->configParser->isSQLite() ? "" : "?", $args);
    }

    /**
     * @return int[][]|null
     */
    public function getInclusions(): ?array
    {
        return $this->getData("inclusions");
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    private function getData(string $key)
    {
        if (!$this->parsed) {
            throw new BadMethodCallException("Before invoking this method, you need to invoke CommandParser::parse()");
        }

        return $this->data[$key];
    }

    /**
     * @return int[][]|null
     */
    public function getExclusions(): ?array
    {
        return $this->getData("exclusions");
    }

    /**
     * @param string $query
     * @param GenericVariable[] $variables
     * @param array $params
     * @param AxisAlignedBB|null $bb
     */
    protected function buildConditionalQuery(string &$query, array &$variables, array &$params, ?AxisAlignedBB $bb): void
    {
        if (!$this->parsed) {
            throw new BadMethodCallException("Before invoking this method, you need to invoke CommandParser::parse()");
        }

        foreach ($this->data as $key => $value) {
            if ($value === null) {
                continue;
            }

            switch ($key) {
                case "user":
                    $variables["users"] = new GenericVariable("users", "list:string", null);
                    $params["users"] = $value;

                    $query .= "(who IN (SELECT uuid FROM entities WHERE entity_name IN :users)) AND ";
                    break;
                case "time":
                    $variables["time"] = new GenericVariable("time", GenericVariable::TYPE_FLOAT, null);
                    $params["time"] = microtime(true) - (float)$value;

                    if ($this->configParser->isSQLite()) {
                        $query .= "(time BETWEEN :time AND (SELECT STRFTIME(\"%s\", \"now\") + STRFTIME(\"%f\", \"now\") - STRFTIME(\"%S\", \"now\"))) AND ";
                    } else {
                        $query .= "(time BETWEEN :time AND UNIX_TIMESTAMP(NOW(4))) AND ";
                    }
                    break;
                case "radius":
                    if ($bb !== null) {
                        $bb = MathUtils::floorBoundingBox($bb);

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

                    break;
                case "action":
                    $variables["actions"] = new GenericVariable("actions", "list:int", null);
                    $params["actions"] = array_map(static function (Action $action): int {
                        return $action->getType();
                    }, $value);

                    $query .= "(action IN :actions) AND ";
                    break;

                case "exclusions":
                    $variables["exclusions_ids"] = new GenericVariable("exclusions_ids", "list:int", null);
                    $params["exclusions_ids"] = $value["ids"];
                    $variables["exclusions_metas"] = new GenericVariable("exclusions_metas", "list:int", null);
                    $params["exclusions_metas"] = $value["metas"];

                    $query .= "(id NOT IN :exclusions_ids OR meta NOT IN :exclusions_metas) AND ";
                    break;

                case "inclusions":
                    $variables["inclusions_ids"] = new GenericVariable("inclusions_ids", "list:int", null);
                    $params["inclusions_ids"] = $value["ids"];
                    $variables["inclusions_metas"] = new GenericVariable("inclusions_metas", "list:int", null);
                    $params["inclusions_metas"] = $value["metas"];

                    $query .= "(id IN :inclusions_ids AND meta IN :inclusions_metas) AND ";
                    break;

                default:
                    throw new UnexpectedValueException("\"$key\" is not a expected data key.");
            }
        }

        $query = mb_substr($query, 0, -5); //Remove excessive " AND " string.
    }

    /**
     * Return string error message when a required parameter is missing.
     * Return null if any parameter is required or all required parameter are present.
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function buildLookupQuery(string &$query, array &$args, ?AxisAlignedBB $bb = null): void
    {
        if (!$this->parsed) {
            throw new BadMethodCallException("Before invoking this method, you need to invoke CommandParser::parse()");
        }

        $query = /**@lang text */
            "SELECT tmp_ids.*, e1.entity_name AS entity_from, e2.entity_name AS entity_to                
            FROM
            (SELECT tmp_logs.*,
                 CASE
                    WHEN tmp_logs.action = 0 OR tmp_logs.action = 6 THEN tmp_logs.new_id
                    WHEN tmp_logs.action = 1 OR tmp_logs.action = 7 THEN tmp_logs.old_id
                    ELSE
                        new_id
                END AS id,
                CASE
                    WHEN tmp_logs.action = 0 OR tmp_logs.action = 6 THEN tmp_logs.new_meta
                    WHEN tmp_logs.action = 1 OR tmp_logs.action = 7 THEN tmp_logs.old_meta
                    ELSE
                        new_meta
                END AS meta
                FROM
                (SELECT log_history.*, old_amount, new_amount,
                    CASE
                        WHEN il.old_id IS NULL THEN bl.old_id
                        WHEN bl.old_id IS NULL THEN il.old_id
                    END AS old_id,
                    CASE
                        WHEN il.old_meta IS NULL THEN bl.old_meta
                        WHEN bl.old_meta IS NULL THEN il.old_meta
                    END AS old_meta,
                    CASE
                        WHEN il.new_id IS NULL THEN bl.new_id
                        WHEN bl.new_id IS NULL THEN il.new_id
                    END AS new_id,
                    CASE
                        WHEN il.new_meta IS NULL THEN bl.new_meta
                        WHEN bl.new_meta IS NULL THEN il.new_meta
                    END AS new_meta
                FROM log_history
                LEFT JOIN blocks_log bl ON log_history.log_id = bl.history_id
                LEFT JOIN inventories_log il ON log_history.log_id = il.history_id
                ) AS tmp_logs
            ) AS tmp_ids
            LEFT JOIN entities_log el ON tmp_ids.log_id = el.history_id
            LEFT JOIN entities e1 ON tmp_ids.who = e1.uuid
            LEFT JOIN entities e2 ON el.entityfrom_uuid = e2.uuid WHERE ";

        /** @var GenericVariable[] $variables */
        $variables = [];
        $parameters = [];

        $this->buildConditionalQuery($query, $variables, $parameters, $bb);

        $query .= " ORDER BY time DESC;";

        $statement = GenericStatementImpl::forDialect(
            $this->configParser->isSQLite() ? SqlDialect::SQLITE : SqlDialect::MYSQL,
            "dyn-lookup-query",
            $query,
            "",
            $variables,
            null,
            0
        );

        $query = $statement->format($parameters, $this->configParser->isSQLite() ? "" : "?", $args);
    }

    /**
     * @return string
     */
    public function getSenderName(): string
    {
        return $this->senderName;
    }

    public function getTime(): ?int
    {
        return $this->getData("time");
    }

    /**
     * It returns an array with the parsed data from the command.
     *
     * @return array
     */
    public function getAllData(): array
    {
        return $this->data;
    }

    /**
     * @return string[]|null
     */
    public function getUsers(): ?array
    {
        return $this->getData("user");
    }

    public function getRadius(): ?int
    {
        return $this->getData("radius");
    }

    public function getDefaultRadius(): int
    {
        return $this->configParser->getDefaultRadius();
    }

    /**
     * @return Action[]|null
     */
    public function getAction(): ?array
    {
        return $this->getData("action");
    }
}
