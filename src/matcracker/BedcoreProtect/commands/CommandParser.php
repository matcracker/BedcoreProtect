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
use matcracker\BedcoreProtect\storage\QueriesConst;
use matcracker\BedcoreProtect\utils\ConfigParser;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\item\ItemFactory;
use pocketmine\math\Vector3;
use pocketmine\Server;
use UnexpectedValueException;

final class CommandParser
{
    public const MAX_PARAMETERS = 6;

    public const ACTIONS = [
        "block" => QueriesConst::PLACED | QueriesConst::BROKE,
        "+block" => QueriesConst::PLACED,
        "-block" => QueriesConst::BROKE,
        "click" => QueriesConst::CLICKED,
        "container" => QueriesConst::ADDED | QueriesConst::REMOVED,
        "+container" => QueriesConst::ADDED,
        "-container" => QueriesConst::REMOVED,
        "kill" => QueriesConst::KILLED,
    ];

    private $configParser;
    private $arguments;
    private $parsed = false;

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
     * @param ConfigParser $configParser
     * @param array $arguments
     * @param bool $shift It shift the first element of array used internally for command arguments. Default false.
     */
    public function __construct(ConfigParser $configParser, array $arguments, bool $shift = false)
    {
        $this->configParser = $configParser;
        $this->arguments = $arguments;
        if ($shift) {
            array_shift($this->arguments);
        }

        $this->data["radius"] = $this->configParser->getDefaultRadius() !== 0 ?? null;
    }

    public function parse(): bool
    {
        if (($c = count($this->arguments)) < 1 || $c > self::MAX_PARAMETERS) return false;

        foreach ($this->arguments as $argument) {
            $arrayData = explode(":", $argument);
            if (count($arrayData) !== 2) return false;
            $param = strtolower($arrayData[0]);
            $paramValues = $arrayData[1];

            if (!is_string($paramValues)) {
                return false;
            }

            switch ($param) {
                case "user":
                case "u":
                    $offlinePlayer = Server::getInstance()->getOfflinePlayer($paramValues);
                    if ($offlinePlayer->hasPlayedBefore()) {
                        $this->data["user"] = $offlinePlayer->getName();
                    }
                    break;
                case "time":
                case "t":
                    $this->data["time"] = Utils::parseTime($paramValues);
                    break;
                case "radius":
                case "r":
                    if (!ctype_digit($paramValues)) return false;
                    $paramValues = (int)$paramValues;
                    $maxRadius = $this->configParser->getMaxRadius();
                    if ($paramValues < 0 || ($maxRadius !== 0 && $paramValues > $maxRadius)) return false;

                    $this->data["radius"] = $paramValues;
                    break;
                case "action":
                case "a":
                    $paramValues = strtolower($paramValues);
                    if (!array_key_exists($paramValues, self::ACTIONS)) return false;

                    $this->data["action"] = $paramValues;
                    break;
                case "blocks":
                case "b":
                case "exclude":
                case "e":
                    $blocks = explode(",", $paramValues);
                    if (count($blocks) < 1) return false;

                    $index = substr($param, 0, 1) === "b" ? "blocks" : "exclusions";
                    foreach ($blocks as $block) {
                        $block = ItemFactory::fromString($block)->getBlock(); //TODO: TEST

                        $this->data[$index][] = [
                            "id" => $block->getId(),
                            "damage" => $block->getDamage()
                        ];
                    }
                    break;
                default:
                    return false;
            }
        }
        $this->parsed = true;
        return true;
    }

    /**
     * It returns a 'select' query to get all optional data from log table
     *
     * @param Vector3 $vector3
     * @param bool $restore
     * @return string
     * @throws UnexpectedValueException if it is used before CommandParser::parse()
     */
    public function buildBlocksLogSelectionQuery(Vector3 $vector3, bool $restore = false): string
    {
        if (!$this->parsed) {
            throw new UnexpectedValueException("Before invoking this method, you need to invoke CommandParser::parse()");
        }

        $prefix = $restore ? "new" : "old";

        $query = /**@lang text */
            "SELECT log_id, bl.{$prefix}_block_id, bl.{$prefix}_block_damage, x, y, z FROM log_history 
            INNER JOIN blocks_log bl ON log_history.log_id = bl.history_id WHERE rollback = '" . (int)$restore . "' AND ";

        $this->buildConditionalQuery($query, $vector3, ["bl.{$prefix}_block_id", "bl.{$prefix}_block_damage"]);

        $query = rtrim($query, " AND ") . " ORDER BY time DESC;";

        return $query;
    }

    private function buildConditionalQuery(string &$query, ?Vector3 $vector3, array $args): void
    {
        if (($cArgs = count($args)) % 2 !== 0 || $cArgs < 1) {
            throw new ArrayOutOfBoundsException("Arguments must be of length equals to 2.");
        }

        foreach ($this->data as $key => $value) {
            if ($key === "user" && $value !== null) {
                $query .= "who = (SELECT uuid FROM entities WHERE entity_name = '$value') AND ";
            } else if ($key === "time" && $value !== null) {
                $diffTime = time() - (int)$value;
                $query .= "(time BETWEEN FROM_UNIXTIME($diffTime) AND CURRENT_TIMESTAMP) AND ";
            } else if ($key === "radius" && $vector3 !== null && $value !== null) {
                $minV = $vector3->subtract($value, $value, $value)->floor();
                $maxV = $vector3->add($value, $value, $value)->floor();
                $query .= "(x BETWEEN '{$minV->getX()}' AND '{$maxV->getX()}') AND ";
                $query .= "(y BETWEEN '{$minV->getY()}' AND '{$maxV->getY()}') AND ";
                $query .= "(z BETWEEN '{$minV->getZ()}' AND '{$maxV->getZ()}') AND ";
            } else if ($key === "action" && $value !== null) {
                $minAction = CommandParser::toAction($value);
                $maxAction = $minAction;
                if ($value === "container") {
                    $minAction = QueriesConst::ADDED;
                    $maxAction = QueriesConst::REMOVED;
                } elseif ($value === "block") {
                    $minAction = QueriesConst::PLACED;
                    $maxAction = QueriesConst::BROKE;
                }
                $query .= "action BETWEEN '{$minAction}' AND '{$maxAction}' AND ";
            } else if (($key === "blocks" || $key === "exclusions") && $value !== null) { //TODO: FIX EXCLUSIONS... I don't know why it doesn't work.
                $operator = $key === "exclusions" ? "<>" : "=";
                for ($i = 0; $i < $cArgs; $i += 2) {
                    foreach ($value as $blockArray) {
                        $id = (int)$blockArray["id"];
                        $damage = (int)$blockArray["damage"];
                        $query .= "{$args[$i]} $operator '$id' AND {$args[$i+1]} $operator '$damage') AND ";
                    }
                }

            }
        }
    }

    public static function toAction(string $cmdAction): int
    {
        if (!isset(self::ACTIONS[$cmdAction]))
            throw new ArrayOutOfBoundsException("The $cmdAction is not a valid action.");

        return self::ACTIONS[$cmdAction];
    }

    public function buildLookupQuery(): string
    {
        if (!$this->parsed) {
            throw new UnexpectedValueException("Before invoking this method, you need to invoke CommandParser::parse()");
        }

        $query = /**@lang text */
            "SELECT *,
            bl.old_block_id, bl.old_block_damage, bl.new_block_id, bl.new_block_damage, 
            il.old_item_id, il.old_item_damage, il.old_amount, il.new_item_id, il.new_item_damage, il.new_amount, 
            e.entity_name AS entity_from FROM log_history 
            LEFT JOIN blocks_log bl ON log_history.log_id = bl.history_id
            LEFT JOIN entities e ON log_history.who = e.uuid 
            LEFT JOIN inventories_log il ON log_history.log_id = il.history_id WHERE ";

        $this->buildConditionalQuery($query, null, [
            "bl.old_block_id", "bl.old_block_damage",
            "bl.new_block_id", "bl.new_block_damage"
        ]);

        $query = rtrim($query, " AND ") . " ORDER BY time DESC;";

        return $query;
    }

    public function buildInventoriesLogSelectionQuery(Vector3 $vector3, bool $restore = false): string
    {
        if (!$this->parsed) {
            throw new UnexpectedValueException("Before invoking this method, you need to invoke CommandParser::parse()");
        }

        $prefix = $restore ? "new" : "old";

        $query = /**@lang text */
            "SELECT log_id, il.slot, il.{$prefix}_item_id, il.{$prefix}_item_damage, il.{$prefix}_amount, x, y, z FROM log_history 
            INNER JOIN inventories_log il ON log_history.log_id = il.history_id WHERE rollback = '" . (int)$restore . "' AND ";

        $this->buildConditionalQuery($query, $vector3, ["il.{$prefix}_item_id", "il.{$prefix}_item_damage"]);

        $query = rtrim($query, " AND ") . " ORDER BY time DESC;";
        return $query;
    }

    /**
     * It returns an array with the parsed data from the command.
     *
     * @return array
     * @throws UnexpectedValueException if it is used before CommandParser::parse()
     */
    public function getData(): array
    {
        if (!$this->parsed) {
            throw new UnexpectedValueException("Before invoking this method, you need to invoke CommandParser::parse()");
        }
        return $this->data;
    }

    public function getUser(): ?string
    {
        return $this->data["user"];
    }

    public function getTime(): ?int
    {
        return $this->data["time"];
    }

    public function getRadius(): ?int
    {
        return $this->data["radius"];
    }

    public function getAction(): ?string
    {
        return $this->data["action"];
    }

    public function getBlocks(): ?array
    {
        return $this->data["blocks"];
    }

    public function getExclusions(): ?array
    {
        return $this->data["exclusions"];
    }
}