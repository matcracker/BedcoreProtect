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

namespace matcracker\BedcoreProtect\storage;

use Generator;
use matcracker\BedcoreProtect\commands\CommandData;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\queries\BlocksQueries;
use matcracker\BedcoreProtect\storage\queries\DefaultQueriesTrait;
use matcracker\BedcoreProtect\storage\queries\EntitiesQueries;
use matcracker\BedcoreProtect\storage\queries\InventoriesQueries;
use matcracker\BedcoreProtect\storage\queries\PluginQueries;
use matcracker\BedcoreProtect\storage\queries\QueriesConst;
use matcracker\BedcoreProtect\utils\MathUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\math\AxisAlignedBB;
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

final class QueryManager
{
    use DefaultQueriesTrait {
        __construct as DefQueriesConstr;
    }

    /** @var array[] */
    private static array $additionalReports = [];
    /** @var AxisAlignedBB[] */
    private static array $activeRollbacks = [];

    private Main $plugin;
    private PluginQueries $pluginQueries;
    private BlocksQueries $blocksQueries;
    private EntitiesQueries $entitiesQueries;
    private InventoriesQueries $inventoriesQueries;
    /** @var UndoRollbackData[] */
    private array $undoData = [];

    public function __construct(Main $plugin, DataConnector $connector)
    {
        $this->DefQueriesConstr($connector);
        $this->plugin = $plugin;

        $this->pluginQueries = new PluginQueries($plugin, $connector);
        $this->entitiesQueries = new EntitiesQueries($plugin, $connector);
        $this->inventoriesQueries = new InventoriesQueries($plugin, $connector);
        $this->blocksQueries = new BlocksQueries($plugin, $connector, $this->entitiesQueries, $this->inventoriesQueries);
    }

    public static function addReportMessage(string $senderName, string $reportMessage): void
    {
        self::$additionalReports[$senderName]["messages"][] = TextFormat::colorize("&f- $reportMessage");
    }

    public function init(string $pluginVersion): void
    {
        if (!preg_match("/^(\d+\.)?(\d+\.)?(\*|\d+)$/", $pluginVersion)) {
            throw new UnexpectedValueException("The field $pluginVersion must be a version.");
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

                /** @var array $rows */
                $rows = yield $this->executeSelect(QueriesConst::GET_DATABASE_STATUS);

                if (count($rows) === 0) {
                    yield $this->executeInsert(QueriesConst::ADD_DATABASE_VERSION, ["version" => $pluginVersion]);
                }
            }
        );

        $this->connector->waitAll();
    }

    public function setupDefaultData(): void
    {
        $this->entitiesQueries->addDefaultEntities();
        $this->connector->waitAll();
    }

    public function rollback(CommandSender $sender, CommandData $cmdData): void
    {
        $this->rawRollback($sender, $cmdData, true);
    }

    /**
     * @param CommandSender $sender
     * @param CommandData $cmdData
     * @param int[]|null $logIds
     * @param bool $rollback
     */
    private function rawRollback(CommandSender $sender, CommandData $cmdData, bool $rollback, ?array $logIds = null): void
    {
        $senderName = $sender->getName();

        if (array_key_exists($senderName, self::$activeRollbacks)) {
            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . TextFormat::RED . "It is not possible to perform more than one rollback at a time."));
            return;
        }

        $worldName = $cmdData->getWorld();
        $world = Server::getInstance()->getLevelByName($worldName);
        if ($world === null) {
            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . TextFormat::RED . "The world \"$worldName\" does not exist or it's unloaded."));
            return;
        }

        if ($sender instanceof Player) {
            //TODO: check if radius could be null
            $bb = MathUtils::getRangedVector($sender->asVector3(), $cmdData->getRadius());

            foreach (self::$activeRollbacks as $rbSender => [$rbBB, $rbWorld]) {
                if ($rbBB->intersectsWith($bb) && $world->getFolderName() === $rbWorld) {
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . TextFormat::RED . "$rbSender is already operating in this area. Try again."));
                    return;
                }
            }
        } else {
            $bb = null;
        }

        self::initAdditionReports($senderName);

        Await::f2c(
            function () use ($sender, $senderName, $cmdData, $world, $worldName, $bb, $rollback, $logIds): Generator {
                /** @var int[] $logIds */
                $logIds = $logIds ?? yield $this->getRollbackLogIds($cmdData, $bb, $rollback);
                if (count($logIds) === 0) { //No changes.
                    self::sendRollbackReport($senderName, $cmdData, $worldName, $rollback);
                    return;
                }

                self::$activeRollbacks[$senderName] = [$bb, $worldName];

                $this->undoData[$senderName] = new UndoRollbackData($rollback, $bb, $cmdData, $logIds);

                $this->getBlocksQueries()->rawRollback(
                    $sender,
                    $cmdData,
                    $world,
                    $rollback,
                    $logIds,
                    function () use ($sender, $senderName, $cmdData, $world, $worldName, $bb, $rollback, $logIds): void {
                        $this->getInventoriesQueries()->rawRollback($sender, $cmdData, $world, $rollback, $logIds, null, false);
                        $this->getEntitiesQueries()->rawRollback(
                            $sender,
                            $cmdData,
                            $world,
                            $rollback,
                            $logIds,
                            static function () use ($senderName): void {
                                unset(self::$activeRollbacks[$senderName]);
                            }
                        );
                    },
                    false
                );
            }
        );
    }

    private static function initAdditionReports(string $senderName): void
    {
        self::$additionalReports[$senderName] = [
            "messages" => [],
            "startTime" => microtime(true)
        ];
    }

    private function getRollbackLogIds(CommandData $cmdData, ?AxisAlignedBB $bb, bool $rollback): Generator
    {
        $query = "";
        $args = [];
        $this->pluginQueries->buildLogsSelectionQuery($query, $args, $cmdData, $bb, $rollback);

        $onSuccess = yield;
        $wrapOnSuccess = function (array $rows) use ($onSuccess): void {
            /** @var int[] $logIds */
            $logIds = [];
            foreach ($rows as $row) {
                $logIds[] = (int)$row["log_id"];
            }
            $onSuccess($logIds);
        };

        $this->connector->executeSelectRaw($query, $args, $wrapOnSuccess, yield Await::REJECT);
        return yield Await::ONCE;
    }

    public static function sendRollbackReport(string $playerName, CommandData $cmdData, string $worldName, bool $rollback): void
    {
        if (!array_key_exists($playerName, self::$additionalReports)) {
            return;
        }


        if (($sender = Server::getInstance()->getPlayer($playerName)) !== null) {
            $date = Utils::timeAgo((int)microtime(true) - $cmdData->getTime());
            $lang = Main::getInstance()->getLanguage();

            $sender->sendMessage(TextFormat::colorize("&f--- &3" . Main::PLUGIN_NAME . "&7 " . $lang->translateString("rollback.report") . " &f---"));
            $sender->sendMessage(TextFormat::colorize($lang->translateString(($rollback ? "rollback" : "restore") . ".completed", [$worldName])));
            $sender->sendMessage(TextFormat::colorize($lang->translateString(($rollback ? "rollback" : "restore") . ".date", [$date])));
            $sender->sendMessage(TextFormat::colorize("&f- " . $lang->translateString("rollback.radius", [$cmdData->getRadius() ?? Main::getInstance()->getParsedConfig()->getMaxRadius()])));

            if (count(self::$additionalReports[$playerName]["messages"]) > 0) {
                foreach (self::$additionalReports[$playerName]["messages"] as $message) {
                    $sender->sendMessage($message);
                }
            } else {
                $sender->sendMessage(TextFormat::colorize("&f- &b" . $lang->translateString("rollback.no-changes")));
            }

            $diff = microtime(true) - self::$additionalReports[$playerName]["startTime"];
            $duration = round($diff, 2);

            $sender->sendMessage(TextFormat::colorize("&f- " . $lang->translateString("rollback.time-taken", [$duration])));
            $sender->sendMessage(TextFormat::colorize("&f------"));
        }

        unset(self::$additionalReports[$playerName]);
    }

    public function getBlocksQueries(): BlocksQueries
    {
        return $this->blocksQueries;
    }

    public function getInventoriesQueries(): InventoriesQueries
    {
        return $this->inventoriesQueries;
    }

    public function getEntitiesQueries(): EntitiesQueries
    {
        return $this->entitiesQueries;
    }

    public function undoRollback(CommandSender $sender): bool
    {
        if (!isset($this->undoData[$sender->getName()])) {
            return false;
        }

        $data = $this->undoData[$sender->getName()];

        $this->rawRollback($sender, $data->getCommandData(), $data->isRollback(), $data->getLogIds());
        return true;
    }

    public function restore(CommandSender $sender, CommandData $cmdData): void
    {
        $this->rawRollback($sender, $cmdData, false);
    }

    public function getPluginQueries(): PluginQueries
    {
        return $this->pluginQueries;
    }
}
