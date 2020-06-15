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

namespace matcracker\BedcoreProtect\commands;

use BadMethodCallException;
use InvalidArgumentException;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\math\MathUtils;
use matcracker\BedcoreProtect\utils\ConfigParser;
use matcracker\BedcoreProtect\utils\EntityUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\math\AxisAlignedBB;
use pocketmine\Server;
use UnexpectedValueException;
use function array_filter;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function array_shift;
use function array_unique;
use function count;
use function ctype_digit;
use function explode;
use function implode;
use function in_array;
use function intval;
use function mb_substr;
use function mb_strtolower;
use function time;

final class CommandParser
{
    public const MAX_PARAMETERS = 6;
    public const PARAMETERS = [
        'user', 'time', 'radius', 'action', 'include', 'exclude'
    ];

    public const ITEM_META_NO_STRICT = 0x8001;

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
    /** @var mixed[] */
    private $data = [
        'user' => null,
        'time' => null,
        'radius' => null,
        'action' => null,
        'inclusions' => null,
        'exclusions' => null
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

        if (($dr = $this->configParser->getDefaultRadius()) !== 0) {
            $this->data['radius'] = $dr;
        }
    }

    /**
     * @internal
     */
    final public static function initActions(): void
    {
        if (self::$ACTIONS === null) {
            self::$ACTIONS = [
                'block' => [Action::PLACE(), Action::BREAK(), Action::SPAWN(), Action::DESPAWN()],
                '+block' => [Action::PLACE()],
                '-block' => [Action::BREAK()],
                'click' => [Action::CLICK()],
                'container' => [Action::ADD(), Action::REMOVE()],
                '+container' => [Action::ADD()],
                '-container' => [Action::REMOVE()],
                'kill' => [Action::KILL()]
            ];
        }
    }

    public function parse(): bool
    {
        $lang = Main::getInstance()->getLanguage();

        if (($c = count($this->arguments)) < 1 || $c > self::MAX_PARAMETERS) {
            $this->errorMessage = $lang->translateString('parser.few-many-parameters', [self::MAX_PARAMETERS]);

            return false;
        }

        foreach ($this->arguments as $argument) {
            $arrayData = explode("=", $argument);
            if (count($arrayData) !== 2) {
                $this->errorMessage = $lang->translateString('parser.invalid-parameter', [implode(', ', self::PARAMETERS)]);
                return false;
            }

            $param = mb_strtolower($arrayData[0]);
            $paramValues = (string)$arrayData[1];

            switch ($param) {
                case 'users':
                case 'user':
                case 'u':
                    $users = explode(',', $paramValues);
                    if (count($users) < 1) {
                        return false;
                    }

                    foreach ($users as &$user) {
                        if (mb_substr($user, 0, 1) === '#') { //Entity
                            $user = mb_substr($user, 1);
                            if (!in_array($user, EntityUtils::getSaveNames())) {
                                $this->errorMessage = $lang->translateString('parser.no-entity', [$user]);

                                return false;
                            }
                        } elseif (!Server::getInstance()->getOfflinePlayer($user)->hasPlayedBefore()) {
                            $this->errorMessage = $lang->translateString('parser.no-player', [$user]);

                            return false;
                        }
                    }
                    $this->data['user'] = array_unique($users);
                    break;
                case 'time':
                case 't':
                    $time = Utils::parseTime($paramValues);
                    if ($time === 0) {
                        $this->errorMessage = $lang->translateString('parser.invalid-amount-time');

                        return false;
                    }
                    $this->data['time'] = $time;
                    break;
                case 'radius':
                case 'r':
                    if (!ctype_digit($paramValues)) {
                        $this->errorMessage = $lang->translateString('parser.invalid-amount-radius');

                        return false;
                    }
                    $paramValues = (int)$paramValues;
                    $maxRadius = $this->configParser->getMaxRadius();
                    if ($paramValues < 0 || ($maxRadius !== 0 && $paramValues > $maxRadius)) {
                        $this->errorMessage = $lang->translateString('parser.invalid-radius');

                        return false;
                    }

                    $this->data['radius'] = $paramValues;
                    break;
                case 'action':
                case 'a':
                    $paramValues = mb_strtolower($paramValues);
                    if (!array_key_exists($paramValues, self::$ACTIONS)) {
                        $this->errorMessage = $lang->translateString('parser.invalid-action', [$paramValues, implode(', ', array_keys(self::$ACTIONS))]);

                        return false;
                    }

                    $this->data['action'] = self::$ACTIONS[$paramValues];
                    break;
                case 'include':
                case 'i':
                case 'exclude':
                case 'e':
                    $index = mb_substr($param, 0, 1) === 'i' ? 'inclusions' : 'exclusions';

                    $items = [];
                    foreach (explode(",", $paramValues) as $strItem) {
                        try {
                            /** @var Item $item */
                            $item = ItemFactory::fromString($strItem);
                        } catch (InvalidArgumentException $exception) {
                            $this->errorMessage = $lang->translateString('parser.invalid-block-' . ($index === 'inclusions' ? 'include' : 'exclude'), [$strItem]);

                            return false;
                        }

                        /*
                         * Allows to include/exclude item in strict mode.
                         * Example:
                         * - include=5      --> includes all blocks with ID 5 (no strict)
                         * - include=5:0    --> includes only block with ID 5 and meta 0 (strict)
                         */
                        $e = explode(":", $strItem);
                        if (!isset($e[1])) {
                            $meta = self::ITEM_META_NO_STRICT;
                        }

                        $items[] = [
                            "id" => $item->getId(),
                            "meta" => $meta ?? $item->getDamage()
                        ];
                    }

                    $this->data[$index] = $items;
                    break;
                default:
                    $this->errorMessage = $lang->translateString('parser.invalid-parameter', [implode(', ', self::PARAMETERS)]);
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
            $this->errorMessage = $lang->translateString('parser.missing-parameters', [implode(',', $this->requiredParams)]);

            return false;
        }

        $this->parsed = true;

        return true;
    }

    public function buildLogsSelectionQuery(bool $rollback, AxisAlignedBB $bb): string
    {
        if (!$this->parsed) {
            throw new BadMethodCallException('Before invoking this method, you need to invoke CommandParser::parse()');
        }

        $case = $innerJoin = "";

        if ($this->getInclusions() !== null || $this->getExclusions() !== null) {
            $case = ",
                    CASE
                        WHEN old_id = 0 THEN new_id
                        WHEN new_id = 0 THEN old_id
                    END AS id,
                    CASE
                        WHEN old_id = 0 THEN new_meta
                        WHEN new_id = 0 THEN old_meta
                    END AS meta";

            $innerJoin = "INNER JOIN blocks_log ON log_history.log_id = history_id";
        }

        $query = /**@lang text */
            "SELECT log_id {$case} FROM log_history {$innerJoin} WHERE rollback = '" . intval(!$rollback) . "' AND ";

        $this->buildConditionalQuery($query, $bb);

        $query .= ' ORDER BY time' . ($rollback ? ' DESC' : '') . ';';

        return $query;
    }

    /**
     * @return int[][]|null
     */
    public function getInclusions(): ?array
    {
        return $this->getData('inclusions');
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    private function getData(string $key)
    {
        if (!$this->parsed) {
            throw new BadMethodCallException('Before invoking this method, you need to invoke CommandParser::parse()');
        }

        return $this->data[$key];
    }

    /**
     * @return int[][]|null
     */
    public function getExclusions(): ?array
    {
        return $this->getData('exclusions');
    }

    protected function buildConditionalQuery(string &$query, ?AxisAlignedBB $bb): void
    {
        if (!$this->parsed) {
            throw new BadMethodCallException('Before invoking this method, you need to invoke CommandParser::parse()');
        }

        foreach ($this->data as $key => $value) {
            if ($value === null) {
                continue;
            }

            switch ($key) {
                case 'user':
                    $query .= '(';
                    foreach ($value as $user) {
                        $query .= "who = (SELECT uuid FROM entities WHERE entity_name = '{$user}') OR ";
                    }
                    $query = mb_substr($query, 0, -4) . ') AND '; //Remove excessive " OR " string.
                    break;
                case 'time':
                    $diffTime = time() - (int)$value;
                    if ($this->configParser->isSQLite()) {
                        $query .= "(time BETWEEN DATETIME('{$diffTime}', 'unixepoch', 'localtime') AND (DATETIME('now', 'localtime'))) AND ";
                    } else {
                        $query .= "(time BETWEEN FROM_UNIXTIME({$diffTime}) AND CURRENT_TIMESTAMP) AND ";
                    }
                    break;
                case 'radius':
                    if ($bb !== null) {
                        $bb = MathUtils::floorBoundingBox($bb);
                        $query .= "(x BETWEEN {$bb->minX} AND {$bb->maxX}) AND ";
                        $query .= "(y BETWEEN {$bb->minY} AND {$bb->maxY}) AND ";
                        $query .= "(z BETWEEN {$bb->minZ} AND {$bb->maxZ}) AND ";
                    }

                    break;
                case 'action':
                    $query .= '(';
                    /** @var Action $action */
                    foreach ($value as $action) {
                        $query .= "action = {$action->getType()} OR ";
                    }
                    $query = mb_substr($query, 0, -4) . ') AND '; //Remove excessive " OR " string.
                    break;
                case 'exclusions':
                case 'inclusions':
                    if ($key === 'exclusions') {
                        $operator = '<>';
                        $condition = 'OR';
                    } else {
                        $operator = '=';
                        $condition = 'AND';
                    }

                    /** @var int[] $blockData */
                    foreach ($value as $blockData) {
                        $id = $blockData["id"];
                        $meta = $blockData["meta"];

                        $query .= "(id {$operator} {$id}";
                        if ($meta !== self::ITEM_META_NO_STRICT) {
                            $query .= " {$condition} meta {$operator} {$meta}";
                        }
                        $query .= ") AND ";
                    }
                    break;
                default:
                    throw new UnexpectedValueException("\"{$key}\" is not a expected data key.");
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

    public function buildLookupQuery(): string
    {
        if (!$this->parsed) {
            throw new BadMethodCallException('Before invoking this method, you need to invoke CommandParser::parse()');
        }

        $query = /**@lang text */
            'SELECT tmp_ids.*, e1.entity_name AS entity_from, e2.entity_name AS entity_to                
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
            LEFT JOIN entities e2 ON el.entityfrom_uuid = e2.uuid WHERE ';

        $this->buildConditionalQuery($query, null);

        $query .= ' ORDER BY time DESC;';

        return $query;
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
        return $this->getData('time');
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
        return $this->getData('user');
    }

    public function getRadius(): ?int
    {
        return $this->getData('radius');
    }

    /**
     * @return Action[]|null
     */
    public function getAction(): ?array
    {
        return $this->getData('action');
    }
}
