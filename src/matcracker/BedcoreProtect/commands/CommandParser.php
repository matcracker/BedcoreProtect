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
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\math\MathUtils;
use matcracker\BedcoreProtect\utils\ConfigParser;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\BlockIds;
use pocketmine\item\Item;
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
        'user' => null,
        'time' => null,
        'radius' => null,
        'action' => null,
        'blocks' => null,
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
    public static function initActions(): void
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
                $this->errorMessage = $lang->translateString('parser.invalid-parameter', [implode(',', array_keys(self::$ACTIONS))]);

                return false;
            }
            $param = strtolower($arrayData[0]);
            $paramValues = $arrayData[1];

            if (!is_string($paramValues)) {
                return false;
            }

            switch ($param) {
                case 'users':
                case 'user':
                case 'u':
                    $users = explode(',', $paramValues);
                    if (count($users) < 1) return false;

                    foreach ($users as &$user) {
                        if (mb_substr($user, 0, 1) === '#') {
                            $user = mb_substr($user, 1);
                            if (!in_array($user, Utils::getEntitySaveNames())) {
                                $this->errorMessage = $lang->translateString('parser.no-entity', [$user]);

                                return false;
                            }
                        } else if (!Server::getInstance()->getOfflinePlayer($user)->hasPlayedBefore()) {
                            $this->errorMessage = $lang->translateString('parser.no-player', [$user]);

                            return false;
                        }
                    }
                    $this->data['user'] = $users;
                    break;
                case 'time':
                case 't':
                    $time = Utils::parseTime($paramValues);
                    if ($time === null) {
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
                    $paramValues = strtolower($paramValues);
                    if (!array_key_exists($paramValues, self::$ACTIONS)) {
                        $this->errorMessage = $lang->translateString('parser.invalid-action');

                        return false;
                    }

                    $this->data['action'] = $paramValues;
                    break;
                case 'blocks':
                case 'b':
                case 'exclude':
                case 'e':
                    $index = mb_substr($param, 0, 1) === 'b' ? 'blocks' : 'exclusions';
                    try {
                        $this->data[$index] =
                            array_filter(
                                array_map(
                                    static function (Item $item): ?Block {
                                        if (($block = $item->getBlock())->getId() === BlockIds::AIR) {
                                            return null;
                                        }
                                        return $block;
                                    }, ItemFactory::fromString($paramValues, true)
                                ),
                                static function (?Block $block): bool {
                                    return $block !== null;
                                }
                            );
                    } catch (InvalidArgumentException $exception) {
                        $this->errorMessage = $lang->translateString('parser.invalid-block-' . ($index === 'blocks' ? 'include' : 'exclude'));

                        return false;
                    }
                    break;
                default:
                    return false;
            }
        }
        $filter = array_filter($this->data, static function ($value): bool {
            return $value !== null;
        });

        if (empty($filter))
            return false;

        if (count(array_intersect_key(array_flip($this->requiredParams), $filter)) !== count($this->requiredParams)) {
            $this->errorMessage = $lang->translateString('parser.missing-parameters', [implode(',', $this->requiredParams)]);

            return false;
        }

        $this->parsed = true;

        return true;
    }

    /**
     * @param bool $restore
     * @param AxisAlignedBB $bb
     * @return string
     */
    public function buildLogsSelectionQuery(bool $restore, AxisAlignedBB $bb): string
    {
        if (!$this->parsed) {
            throw new BadMethodCallException('Before invoking this method, you need to invoke CommandParser::parse()');
        }

        $restore = intval($restore);
        $query = /**@lang text */
            "SELECT log_id FROM log_history WHERE rollback = '{$restore}' AND ";

        $this->buildConditionalQuery($query, $bb);

        $query .= ' ORDER BY time DESC;';

        return $query;
    }

    protected function buildConditionalQuery(string &$query, ?AxisAlignedBB $bb): void
    {
        if (!$this->parsed) {
            throw new BadMethodCallException('Before invoking this method, you need to invoke CommandParser::parse()');
        }

        foreach ($this->data as $key => $value) {
            if ($value !== null) {
                if ($key === 'user') {
                    foreach ($value as $user) {
                        $query .= "who = (SELECT uuid FROM entities WHERE entity_name = '{$user}') OR ";
                    }
                    $query = mb_substr($query, 0, -4) . ' AND '; //Remove excessive " OR " string.
                } else if ($key === 'time') {
                    $diffTime = time() - (int)$value;
                    if ($this->configParser->isSQLite()) {
                        $query .= "(time BETWEEN DATETIME('{$diffTime}', 'unixepoch', 'localtime') AND (DATETIME('now', 'localtime'))) AND ";
                    } else {
                        $query .= "(time BETWEEN FROM_UNIXTIME({$diffTime}) AND CURRENT_TIMESTAMP) AND ";
                    }
                } else if ($key === 'radius' && $bb !== null) {
                    $bb = MathUtils::floorBoundingBox($bb);
                    $query .= "(x BETWEEN '{$bb->minX}' AND '{$bb->maxX}') AND ";
                    $query .= "(y BETWEEN '{$bb->minY}' AND '{$bb->maxY}') AND ";
                    $query .= "(z BETWEEN '{$bb->minZ}' AND '{$bb->maxZ}') AND ";
                } else if ($key === 'action') {
                    $actions = CommandParser::toActions($value);
                    foreach ($actions as $action) {
                        $query .= "action = {$action->getType()} OR ";
                    }
                    $query = mb_substr($query, 0, -4) . ' AND '; //Remove excessive " OR " string.
                }/* else if ($key === 'blocks' || $key === 'exclusions') {
                    $operator = $key === 'exclusions' ? '<>' : '=';
                    /**@var Block $block *
                    foreach ($value as $block) {
                        $id = $block->getId();
                        $meta = $block->getDamage();
                        $query .= "(old_id {$operator} '{$id}' AND old_meta {$operator} '{$meta}') AND ";
                    }
                }*/
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
            throw new ArrayOutOfBoundsException("The {$cmdAction} is not a valid action.");

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
            throw new BadMethodCallException('Before invoking this method, you need to invoke CommandParser::parse()');
        }

        $query = /**@lang text */
            'SELECT *,
            bl.old_id, bl.old_meta, bl.new_id, bl.new_meta, 
            il.old_id, il.old_meta, il.old_amount, il.new_id, il.new_meta, il.new_amount, 
            e.entity_name AS entity_from FROM log_history 
            LEFT JOIN blocks_log bl ON log_history.log_id = bl.history_id 
            LEFT JOIN entities e ON log_history.who = e.uuid 
            LEFT JOIN inventories_log il ON log_history.log_id = il.history_id WHERE ';

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
        return $this->getData('user');
    }

    public function getRadius(): ?int
    {
        return $this->getData('radius');
    }

    public function getAction(): ?string
    {
        return $this->getData("action");
    }

    /**
     * @return Block[]|null
     */
    public function getBlocks(): ?array
    {
        return $this->getData('blocks');
    }

    /**
     * @return Block[]|null
     */
    public function getExclusions(): ?array
    {
        return $this->getData('exclusions');
    }
}