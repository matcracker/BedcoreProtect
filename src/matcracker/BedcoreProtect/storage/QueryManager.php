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

namespace matcracker\BedcoreProtect\storage;

use Generator;
use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\math\Area;
use matcracker\BedcoreProtect\storage\queries\BlocksQueries;
use matcracker\BedcoreProtect\storage\queries\EntitiesQueries;
use matcracker\BedcoreProtect\storage\queries\InventoriesQueries;
use matcracker\BedcoreProtect\storage\queries\PluginQueries;
use matcracker\BedcoreProtect\storage\queries\QueriesConst;
use matcracker\BedcoreProtect\utils\UndoRollbackData;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Await;
use UnexpectedValueException;
use function array_key_exists;
use function count;
use function microtime;
use function preg_match;
use function round;
use function time;

final class QueryManager
{
    /** @var mixed[][] */
    private static $additionalReports = [];
    /** @var Area[] */
    private static $activeRollbacks = [];

    /** @var Main */
    private $plugin;
    /** @var DataConnector */
    private $connector;
    /** @var PluginQueries */
    private $pluginQueries;
    /** @var BlocksQueries */
    private $blocksQueries;
    /** @var EntitiesQueries */
    private $entitiesQueries;
    /** @var InventoriesQueries */
    private $inventoriesQueries;
    /** @var UndoRollbackData[] */
    private $undoData = [];

    public function __construct(Main $plugin, DataConnector $connector)
    {
        $this->plugin = $plugin;
        $this->connector = $connector;

        $this->pluginQueries = new PluginQueries($plugin, $connector);
        $this->entitiesQueries = new EntitiesQueries($plugin, $connector);
        $this->inventoriesQueries = new InventoriesQueries($plugin, $connector);
        $this->blocksQueries = new BlocksQueries($plugin, $connector, $this->entitiesQueries, $this->inventoriesQueries);
    }

    public static function addReportMessage(string $senderName, string $reportMessage, array $params = []): void
    {
        $lang = Main::getInstance()->getLanguage();

        self::$additionalReports[$senderName]['messages'][] = TextFormat::colorize('&f- ' . $lang->translateString($reportMessage, $params));
    }

    private static function initAdditionReports(string $senderName): void
    {
        self::$additionalReports[$senderName] = [
            'messages' => [],
            'startTime' => microtime(true)
        ];
    }

    public function init(string $pluginVersion): void
    {
        if (!preg_match('/^(\d+\.)?(\d+\.)?(\*|\d+)$/', $pluginVersion)) {
            throw new UnexpectedValueException("The field {$pluginVersion} must be a version.");
        }

        Await::f2c(
            function () use ($pluginVersion): Generator {
                if ($this->plugin->getParsedConfig()->isSQLite()) {
                    yield $this->executeGeneric(QueriesConst::ENABLE_WAL_MODE);
                    yield $this->executeGeneric(QueriesConst::SET_SYNC_NORMAL);
                }

                yield $this->executeGeneric(QueriesConst::SET_FOREIGN_KEYS, ["flag" => true]);

                foreach (QueriesConst::INIT_TABLES as $queryTable) {
                    yield $this->executeGeneric($queryTable);
                }

                yield $this->executeInsert(QueriesConst::ADD_DATABASE_VERSION, ['version' => $pluginVersion]);
            }
        );

        $this->connector->waitAll();
    }

    private function executeGeneric(string $query, array $args = []): Generator
    {
        $this->connector->executeGeneric($query, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    private function executeInsert(string $query, array $args = []): Generator
    {
        $this->connector->executeInsert($query, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    public function setupDefaultData(): void
    {
        $this->entitiesQueries->addDefaultEntities();
        $this->connector->waitAll();
    }

    public function rollback(Area $area, CommandParser $commandParser): void
    {
        $this->rawRollback(true, $area, $commandParser);
    }

    /**
     * @param bool $rollback
     * @param Area $area
     * @param CommandParser $commandParser
     * @param int[]|null $logIds
     */
    private function rawRollback(bool $rollback, Area $area, CommandParser $commandParser, ?array $logIds = null): void
    {
        $senderName = $commandParser->getSenderName();
        $player = Server::getInstance()->getPlayer($senderName);

        if (array_key_exists($senderName, self::$activeRollbacks)) {
            if ($player !== null) {
                $player->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . TextFormat::RED . "It is not possible to perform more than one rollback at a time."));
            }
            return;
        }

        foreach (self::$activeRollbacks as $rbSender => $activeRollback) {
            if ($activeRollback->getBoundingBox()->intersectsWith($area->getBoundingBox())) {
                if ($player !== null) {
                    $player->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . TextFormat::RED . "{$rbSender} is already operating in this area. Try again."));
                }
                return;
            }
        }

        self::initAdditionReports($senderName);

        Await::f2c(
            function () use ($rollback, $area, $commandParser, $senderName, $logIds) : Generator {
                /** @var int[] $logIds */
                $logIds = $logIds ?? yield $this->getRollbackLogIds($rollback, $area, $commandParser);
                if (count($logIds) === 0) { //No changes.
                    self::sendRollbackReport($rollback, $area, $commandParser);
                    return;
                }

                self::$activeRollbacks[$senderName] = $area;
                $this->undoData[$senderName] = new UndoRollbackData($rollback, $area, $commandParser, $logIds);

                $this->getBlocksQueries()->rawRollback(
                    $rollback,
                    $area,
                    $commandParser,
                    $logIds,
                    function () use ($rollback, $area, $commandParser, $logIds): void {
                        $this->getInventoriesQueries()->rawRollback($rollback, $area, $commandParser, $logIds, null, false);
                        $this->getEntitiesQueries()->rawRollback(
                            $rollback,
                            $area,
                            $commandParser,
                            $logIds,
                            static function () use ($commandParser) {
                                unset(self::$activeRollbacks[$commandParser->getSenderName()]);
                            }
                        );
                    },
                    false
                );
            }
        );
    }

    private function getRollbackLogIds(bool $rollback, Area $area, CommandParser $commandParser): Generator
    {
        $query = $commandParser->buildLogsSelectionQuery($rollback, $area->getBoundingBox());
        $onSuccess = yield;
        $wrapOnSuccess = function (array $rows) use ($onSuccess) {
            /** @var int[] $logIds */
            $logIds = [];
            foreach ($rows as $row) {
                $logIds[] = (int)$row['log_id'];
            }
            $onSuccess($logIds);
        };

        $this->connector->executeSelectRaw($query, [], $wrapOnSuccess, yield Await::REJECT);
        return yield Await::ONCE;
    }

    public static function sendRollbackReport(bool $rollback, Area $area, CommandParser $commandParser): void
    {
        $senderName = $commandParser->getSenderName();
        if (!array_key_exists($senderName, self::$additionalReports)) {
            return;
        }

        if (($sender = Server::getInstance()->getPlayer($senderName)) !== null) {
            $date = Utils::timeAgo(time() - $commandParser->getTime());
            $lang = Main::getInstance()->getLanguage();

            $sender->sendMessage(TextFormat::colorize('&f--- &3' . Main::PLUGIN_NAME . '&7 ' . $lang->translateString('rollback.report') . ' &f---'));
            $sender->sendMessage(TextFormat::colorize($lang->translateString(($rollback ? 'rollback' : 'restore') . '.completed', [$area->getWorld()->getName()])));
            $sender->sendMessage(TextFormat::colorize($lang->translateString(($rollback ? 'rollback' : 'restore') . '.date', [$date])));
            $sender->sendMessage(TextFormat::colorize('&f- ' . $lang->translateString('rollback.radius', [$commandParser->getRadius() ?? 0])));

            if (count(self::$additionalReports[$senderName]['messages']) > 0) {
                foreach (self::$additionalReports[$senderName]['messages'] as $message) {
                    $sender->sendMessage($message);
                }
            } else {
                $sender->sendMessage(TextFormat::colorize('&f- &b' . $lang->translateString('rollback.no-changes')));
            }

            $diff = microtime(true) - self::$additionalReports[$senderName]['startTime'];
            $duration = round($diff, 2);

            $sender->sendMessage(TextFormat::colorize('&f- ' . $lang->translateString('rollback.time-taken', [$duration])));
            $sender->sendMessage(TextFormat::colorize('&f------'));
        }

        unset(self::$additionalReports[$senderName]);
    }

    /**
     * @return BlocksQueries
     */
    public function getBlocksQueries(): BlocksQueries
    {
        return $this->blocksQueries;
    }

    /**
     * @return InventoriesQueries
     */
    public function getInventoriesQueries(): InventoriesQueries
    {
        return $this->inventoriesQueries;
    }

    /**
     * @return EntitiesQueries
     */
    public function getEntitiesQueries(): EntitiesQueries
    {
        return $this->entitiesQueries;
    }

    public function undoRollback(Player $player): bool
    {
        if (!isset($this->undoData[$player->getName()])) {
            return false;
        }

        $data = $this->undoData[$player->getName()];

        $this->rawRollback($data->isRollback(), $data->getArea(), $data->getCommandParser(), $data->getLogIds());
        return true;
    }

    public function restore(Area $area, CommandParser $commandParser): void
    {
        $this->rawRollback(false, $area, $commandParser);
    }

    /**
     * @return PluginQueries
     */
    public function getPluginQueries(): PluginQueries
    {
        return $this->pluginQueries;
    }
}
