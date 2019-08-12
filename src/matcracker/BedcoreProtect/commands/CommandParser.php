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

use ArrayOutOfBoundsException;
use BadMethodCallException;
use InvalidArgumentException;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\utils\ConfigParser;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\item\ItemFactory;
use pocketmine\math\AxisAlignedBB;
use pocketmine\Server;

CommandParser::initActions();

final class CommandParser
{
    public const MAX_PARAMETERS = 6;

    /**@var Action[][] */
    public static $ACTIONS;

    /**@var string $senderName */
    private $senderName;
    /**@var ConfigParser $configParser */
    private $configParser;
    /**@var string[] $arguments */
    private $arguments;
    /**@var string[] $requiredParams */
    private $requiredParams;
    /**@var bool $parsed */
    private $parsed = false;
    /**@var string $errorMessage */
    private $errorMessage;

    //Default data values
    private $data = [
        "user" => null,
        "time" => null,
        "radius" => null,
        "action" => null,
        "blocks" => null,
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

        if (($dr = $this->configParser->getDefaultRadius()) !== 0) {
            $this->data["radius"] = $dr;
        }
    }

    /**
     * @internal
     */
    public static function initActions(): void
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
        if (($c = count($this->arguments)) < 1 || $c > self::MAX_PARAMETERS) {
            $this->errorMessage = "You are using too few or too many parameters (Max: " . self::MAX_PARAMETERS . ")";

            return false;
        }

        foreach ($this->arguments as $argument) {
            $arrayData = explode("=", $argument);
            if (count($arrayData) !== 2) {
                $this->errorMessage = "Please specify a valid parameter. (" . implode(",", array_keys(self::$ACTIONS)) . ").";

                return false;
            }
            $param = strtolower($arrayData[0]);
            $paramValues = $arrayData[1];

            if (!is_string($paramValues)) {
                return false;
            }

            switch ($param) {
                case "users":
                case "user":
                case "u":
                    $users = explode(",", $paramValues);
                    if (count($users) < 1) return false;

                    foreach ($users as &$user) {
                        if (mb_substr($user, 0, 1) === "#") {
                            $user = mb_substr($user, 1);
                            if (!in_array($user, Utils::getEntitySaveNames())) {
                                $this->errorMessage = "The entity \"{$user}\" does not exist. (The name is case-sensitive)";

                                return false;
                            }
                        } else if (!Server::getInstance()->getOfflinePlayer($user)->hasPlayedBefore()) {
                            $this->errorMessage = "The player \"{$user}\" does not exist.";

                            return false;
                        }
                    }
                    $this->data["user"] = $users;
                    break;
                case "time":
                case "t":
                    $time = Utils::parseTime($paramValues);
                    if ($time === null) {
                        $this->errorMessage = "Please specify the amount of time.";

                        return false;
                    }
                    $this->data["time"] = $time;
                    break;
                case "radius":
                case "r":
                    if (!ctype_digit($paramValues)) {
                        $this->errorMessage = "Please specify the amount of radius.";

                        return false;
                    }
                    $paramValues = (int)$paramValues;
                    $maxRadius = $this->configParser->getMaxRadius();
                    if ($paramValues < 0 || ($maxRadius !== 0 && $paramValues > $maxRadius)) {
                        $this->errorMessage = "Please specify a valid radius.";

                        return false;
                    }

                    $this->data["radius"] = $paramValues;
                    break;
                case "action":
                case "a":
                    $paramValues = strtolower($paramValues);
                    if (!array_key_exists($paramValues, self::$ACTIONS)) {
                        $this->errorMessage = "Please specify a valid action.";

                        return false;
                    }

                    $this->data["action"] = $paramValues;
                    break;
                case "blocks":
                case "b":
                case "exclude":
                case "e":
                    $blocks = explode(",", $paramValues);
                    if (count($blocks) < 1) return false;

                    $index = mb_substr($param, 0, 1) === "b" ? "blocks" : "exclusions";
                    foreach ($blocks as $block) {
                        try {
                            $block = ItemFactory::fromString($block)->getBlock();

                            $this->data[$index][] = [
                                "id" => $block->getId(),
                                "meta" => $block->getDamage()
                            ];
                        } catch (InvalidArgumentException $exception) {
                            $this->errorMessage = "Invalid block \"{$block}\" to " . ($index === "blocks" ? "include" : "exclude") . ".";

                            return false;
                        }
                    }
                    break;
                default:
                    return false;
            }
        }
        $filter = array_filter($this->data, static function ($value) {
            return $value !== null;
        });

        if (empty($filter))
            return false;

        if (count(array_intersect_key(array_flip($this->requiredParams), $filter)) !== count($this->requiredParams)) {
            $this->errorMessage = "You are missing one of the following parameters: " . implode(",", $this->requiredParams);

            return false;
        }

        $this->parsed = true;

        return true;
    }

    /**
     * It returns a 'select' query to get all optional data from log table
     *
     * @param AxisAlignedBB $vector3
     * @param bool $restore
     *
     * @return string
     */
    public function buildBlocksLogSelectionQuery(AxisAlignedBB $vector3, bool $restore = false): string
    {
        if (!$this->parsed) {
            throw new BadMethodCallException("Before invoking this method, you need to invoke CommandParser::parse()");
        }

        $prefix = $restore ? "new" : "old";
        $clickAction = Action::CLICK()->getType();
        $query = /**@lang text */
            "SELECT log_id, bl.{$prefix}_block_id, bl.{$prefix}_block_meta, bl.{$prefix}_block_nbt, x, y, z, world_name FROM log_history 
            INNER JOIN blocks_log bl ON log_history.log_id = bl.history_id WHERE rollback = '" . (int)$restore . "' AND action <> '{$clickAction}' AND ";

        $this->buildConditionalQuery($query, $vector3, ["bl.{$prefix}_block_id", "bl.{$prefix}_block_meta"]);

        $query .= " ORDER BY time DESC;";

        return $query;
    }

    private function buildConditionalQuery(string &$query, ?AxisAlignedBB $bb, ?array $args): void
    {
        $cArgs = -1;
        if ($args !== null && (($cArgs = count($args)) % 2 !== 0 || $cArgs < 1)) {
            throw new ArrayOutOfBoundsException("Arguments must be of length equals to 2.");
        }

        foreach ($this->data as $key => $value) {
            if ($value !== null) {
                if ($key === "user") {
                    foreach ($value as $user) {
                        $query .= "who = (SELECT uuid FROM entities WHERE entity_name = '{$user}') OR ";
                    }
                    $query = mb_substr($query, 0, -4) . " AND "; //Remove excessive " OR " string.
                } else if ($key === "time") {
                    $diffTime = time() - (int)$value;
                    if ($this->configParser->isSQLite()) {
                        $query .= "(time BETWEEN DATETIME('{$diffTime}', 'unixepoch', 'localtime') AND (DATETIME('now', 'localtime'))) AND ";
                    } else {
                        $query .= "(time BETWEEN FROM_UNIXTIME({$diffTime}) AND CURRENT_TIMESTAMP) AND ";
                    }
                } else if ($key === "radius" && $bb !== null) {
                    $query .= "(x BETWEEN '{$bb->minX}' AND '{$bb->maxX}') AND ";
                    $query .= "(y BETWEEN '{$bb->minY}' AND '{$bb->maxY}') AND ";
                    $query .= "(z BETWEEN '{$bb->minZ}' AND '{$bb->maxZ}') AND ";
                } else if ($key === "action") {
                    $actions = CommandParser::toActions($value);
                    foreach ($actions as $action) {
                        $query .= "action = {$action->getType()} OR ";
                    }
                    $query = mb_substr($query, 0, -4) . " AND "; //Remove excessive " OR " string.
                } else if (($key === "blocks" || $key === "exclusions") && $cArgs > 0) {
                    $operator = $key === "exclusions" ? "<>" : "=";
                    for ($i = 0; $i < $cArgs; $i += 2) {
                        foreach ($value as $blockArray) {
                            $id = (int)$blockArray["id"];
                            $meta = (int)$blockArray["meta"];
                            $query .= "({$args[$i]} {$operator} '{$id}' AND {$args[$i+1]} {$operator} '{$meta}') AND ";
                        }
                    }

                }
            }
        }

        $query = mb_substr($query, 0, -5); //Remove excessive " AND " string.
    }

    /**
     * @param string $cmdAction
     *
     * @return Action[]
     */
    public static function toActions(string $cmdAction): array
    {
        if (!isset(self::$ACTIONS[$cmdAction]))
            throw new ArrayOutOfBoundsException("The $cmdAction is not a valid action.");

        return self::$ACTIONS[$cmdAction];
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
            throw new BadMethodCallException("Before invoking this method, you need to invoke CommandParser::parse()");
        }

        $query = /**@lang text */
            "SELECT *,
            bl.old_block_id, bl.old_block_meta, bl.new_block_id, bl.new_block_meta, 
            il.old_item_id, il.old_item_meta, il.old_item_amount, il.new_item_id, il.new_item_meta, il.new_item_amount, 
            e.entity_name AS entity_from FROM log_history 
            LEFT JOIN blocks_log bl ON log_history.log_id = bl.history_id 
            LEFT JOIN entities e ON log_history.who = e.uuid 
            LEFT JOIN inventories_log il ON log_history.log_id = il.history_id WHERE ";

        $this->buildConditionalQuery($query, null, [
            "bl.old_block_id", "bl.old_block_meta",
            "bl.new_block_id", "bl.new_block_meta"
        ]);

        $query .= " ORDER BY time DESC;";

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
        return $this->getData("time");
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

    public function buildInventoriesLogSelectionQuery(AxisAlignedBB $bb, bool $restore = false): string
    {
        if (!$this->parsed) {
            throw new BadMethodCallException("Before invoking this method, you need to invoke CommandParser::parse()");
        }

        $prefix = $restore ? "new" : "old";

        $query = /**@lang text */
            "SELECT log_id, il.slot, il.{$prefix}_item_id, il.{$prefix}_item_meta, il.{$prefix}_item_nbt, il.{$prefix}_item_amount, x, y, z FROM log_history 
            INNER JOIN inventories_log il ON log_history.log_id = il.history_id WHERE rollback = '{$restore}' AND ";

        $this->buildConditionalQuery($query, $bb, ["il.{$prefix}_item_id", "il.{$prefix}_item_meta"]);

        $query .= " ORDER BY time DESC;";

        return $query;
    }

    public function buildEntitiesLogSelectionQuery(AxisAlignedBB $bb, bool $restore = false): string
    {
        if (!$this->parsed) {
            throw new BadMethodCallException("Before invoking this method, you need to invoke CommandParser::parse()");
        }

        $query = /**@lang text */
            "SELECT log_id, e.entity_classpath, el.entityfrom_id, el.entityfrom_nbt, x, y, z, action FROM log_history 
            INNER JOIN entities_log el ON log_history.log_id = el.history_id
            INNER JOIN entities e ON e.uuid = el.entityfrom_uuid
            WHERE rollback = '{$restore}' AND ";

        $this->buildConditionalQuery($query, $bb, null);

        $query .= " ORDER BY time DESC;";

        return $query;
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

    public function getUsers(): ?string
    {
        return $this->getData("user");
    }

    public function getRadius(): ?int
    {
        return $this->getData("radius");
    }

    public function getAction(): ?string
    {
        return $this->getData("action");
    }

    public function getBlocks(): ?array
    {
        return $this->getData("blocks");
    }

    public function getExclusions(): ?array
    {
        return $this->getData("exclusions");
    }
}