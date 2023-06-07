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
use matcracker\BedcoreProtect\storage\queries\EntitiesQueries;
use matcracker\BedcoreProtect\storage\queries\InventoriesQueries;
use matcracker\BedcoreProtect\storage\queries\PlayerQueries;
use matcracker\BedcoreProtect\storage\queries\PluginQueries;
use matcracker\BedcoreProtect\storage\queries\QueriesConst;
use matcracker\BedcoreProtect\utils\MathUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use poggit\libasynql\DataConnector;
use poggit\libasynql\result\SqlSelectResult;
use poggit\libasynql\SqlThread;
use SOFe\AwaitGenerator\Await;
use function array_key_exists;
use function array_merge;
use function count;
use function microtime;
use function round;

final class QueryManager
{

    private const ROLLBACK_ROWS_LIMIT = 25000;

    private static array $activeRollbacks = [];
    /** @var UndoRollbackData[] */
    private static array $undoData = [];

    private PluginQueries $pluginQueries;
    private BlocksQueries $blocksQueries;
    private EntitiesQueries $entitiesQueries;
    private PlayerQueries $playerQueries;

    private InventoriesQueries $inventoriesQueries;

    public function __construct(private readonly Main $plugin, private readonly DataConnector $connector)
    {
        $this->pluginQueries = new PluginQueries($plugin, $connector);
        $this->entitiesQueries = new EntitiesQueries($plugin, $connector);
        $this->inventoriesQueries = new InventoriesQueries($plugin, $connector);
        $this->playerQueries = new PlayerQueries($this->plugin, $this->connector, $this->entitiesQueries);
        $this->blocksQueries = new BlocksQueries($plugin, $connector, $this->entitiesQueries, $this->inventoriesQueries);
    }

    public function rollback(CommandSender $sender, CommandData $cmdData): void
    {
        $this->rawRollback($sender, $cmdData, true);
    }

    /**
     * @param CommandSender $sender
     * @param CommandData $cmdData
     * @param bool $rollback
     * @param float|null $undoTime
     */
    private function rawRollback(CommandSender $sender, CommandData $cmdData, bool $rollback, ?float $undoTime = null): void
    {
        $senderName = $sender->getName();

        if (array_key_exists($senderName, self::$activeRollbacks)) {
            $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->plugin->getLanguage()->translateString("rollback.error.rollback-in-progress"));
            return;
        }

        $worldName = $cmdData->getWorld();
        $world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);
        if ($world === null) {
            $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->plugin->getLanguage()->translateString("rollback.error.world-not-exist", [$worldName]));
            return;
        }

        $bb = $sender instanceof Player ? MathUtils::getRangedVector($sender->getPosition(), $cmdData->getRadius()) : null;

        foreach (self::$activeRollbacks as $actSender => [$actBB, $actWorld]) {
            if ((($bb !== null && $actBB !== null && $actBB->intersectsWith($bb)) || $cmdData->isGlobalRadius()) && $worldName === $actWorld) {
                $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->plugin->getLanguage()->translateString("rollback.error.rollback-area-in-progress", [$actSender]));
                return;
            }
        }

        $startTime = microtime(true);
        $time = $undoTime ?? $startTime;

        Await::f2c(
            function () use ($sender, $senderName, $cmdData, $world, $worldName, $bb, $rollback, $time, $startTime): Generator {
                self::$activeRollbacks[$senderName] = [$bb, $worldName];
                $executed = false;
                /** @var array<int, Vector3> $blockUpdatesPos */
                $blockUpdatesPos = [];
                $blocks = $chunks = $items = $entities = 0;

                /** @var int[] $logIds */
                while (count($logIds = yield from $this->getRollbackLogIds($cmdData, $bb, $time, $rollback, self::ROLLBACK_ROWS_LIMIT)) !== 0) {
                    [$tmpBlocks, $tmpChunks, $tmpBlockUpdPos] = yield from $this->getBlocksQueries()->onRollback($sender, $world, $rollback, $logIds);
                    $blocks += $tmpBlocks;
                    $chunks += $tmpChunks;
                    $blockUpdatesPos = array_merge($blockUpdatesPos, $tmpBlockUpdPos);

                    [$tmpItems, $tmpEntities] = yield from Await::all([
                        $this->getInventoriesQueries()->onRollback($sender, $world, $rollback, $logIds),
                        $this->getEntitiesQueries()->onRollback($sender, $world, $rollback, $logIds)
                    ]);
                    $items += $tmpItems;
                    $entities += $tmpEntities;

                    yield from $this->connector->asyncChange(QueriesConst::UPDATE_ROLLBACK_STATUS, [
                        "rollback" => $rollback,
                        "log_ids" => $logIds
                    ]);

                    $executed = true;
                }

                foreach ($blockUpdatesPos as $blockUpdatePos) {
                    $world->notifyNeighbourBlockUpdate($blockUpdatePos);
                }

                if ($executed) {
                    self::$undoData[$senderName] = new UndoRollbackData($rollback, $cmdData, $time);
                }

                if ($sender !== null) {
                    $this->sendRollbackReport($sender, $cmdData, $rollback, $blocks, $chunks, $items, $entities, $startTime);
                }
            },
            static function () use ($senderName): void {
                unset(self::$activeRollbacks[$senderName]);
            }
        );
    }

    private function getRollbackLogIds(CommandData $cmdData, ?AxisAlignedBB $bb, float $currTime, bool $rollback, int $limit): Generator
    {
        $query = "";
        $args = [[]];
        $this->pluginQueries->buildLogsSelectionQuery($query, $args, $cmdData, $bb, $currTime, $rollback, $limit);

        $onSuccess = yield Await::RESOLVE;
        /** @var SqlSelectResult[] $results */
        $wrapOnSuccess = static function (array $results) use ($onSuccess): void {
            $result = $results[count($results) - 1];
            /** @var int[] $logIds */
            $logIds = [];
            foreach ($result->getRows() as $row) {
                $logIds[] = (int)$row["log_id"];
            }
            $onSuccess($logIds);
        };

        $this->connector->executeImplRaw([$query], $args, [SqlThread::MODE_SELECT], $wrapOnSuccess, yield Await::REJECT);
        return yield Await::ONCE;
    }

    public function getBlocksQueries(): BlocksQueries
    {
        return $this->blocksQueries;
    }

    public function getInventoriesQueries(): InventoriesQueries
    {
        return $this->inventoriesQueries;
    }

    public function getPlayerQueries(): PlayerQueries
    {
        return $this->playerQueries;
    }

    public function getEntitiesQueries(): EntitiesQueries
    {
        return $this->entitiesQueries;
    }

    private function sendRollbackReport(CommandSender $sender, CommandData $cmdData, bool $rollback, int $blocks, int $chunks, int $items, int $entities, float $startTime): void
    {
        $date = Utils::timeAgo((int)microtime(true) - $cmdData->getTime());
        $lang = $this->plugin->getLanguage();

        $sender->sendMessage(TextFormat::WHITE . "--- " . TextFormat::DARK_AQUA . Main::PLUGIN_NAME . TextFormat::GRAY . " " . $this->plugin->getLanguage()->translateString("rollback.report") . TextFormat::WHITE . " ---");
        $sender->sendMessage(TextFormat::WHITE . $lang->translateString(($rollback ? "rollback" : "restore") . ".completed", [$cmdData->getWorld()]));
        $sender->sendMessage(TextFormat::WHITE . $lang->translateString(($rollback ? "rollback" : "restore") . ".date", [$date]));
        if ($cmdData->isGlobalRadius()) {
            $sender->sendMessage(TextFormat::WHITE . "- " . $lang->translateString("rollback.global-radius"));
        } else {
            $sender->sendMessage(TextFormat::WHITE . "- " . $lang->translateString("rollback.radius", [$cmdData->getRadius() ?? $this->plugin->getParsedConfig()->getMaxRadius()]));
        }

        if ($blocks + $items + $entities === 0) {
            $sender->sendMessage(TextFormat::WHITE . "- " . TextFormat::DARK_AQUA . $this->plugin->getLanguage()->translateString("rollback.no-changes"));
        } else {
            if ($blocks > 0) {
                $sender->sendMessage(TextFormat::WHITE . "- " . $lang->translateString("rollback.blocks", [$blocks]));
                $sender->sendMessage(TextFormat::WHITE . "- " . $lang->translateString("rollback.modified-chunks", [$chunks]));
            }

            if ($items > 0) {
                $sender->sendMessage(TextFormat::WHITE . "- " . $lang->translateString("rollback.items", [$items]));
            }

            if ($entities > 0) {
                $sender->sendMessage(TextFormat::WHITE . "- " . $lang->translateString("rollback.entities", [$entities]));
            }
        }

        $diff = microtime(true) - $startTime;
        $duration = round($diff, 2);

        $sender->sendMessage(TextFormat::WHITE . "- " . $lang->translateString("rollback.time-taken", [$duration]));
        $sender->sendMessage(TextFormat::WHITE . "------");
    }

    public function undoRollback(CommandSender $sender): bool
    {
        $data = self::getUndoData($sender);
        if ($data === null) {
            return false;
        }

        $this->rawRollback($sender, $data->getCommandData(), $data->isRollback(), $data->getStartTime());
        return true;
    }

    public static function getUndoData(CommandSender $sender): ?UndoRollbackData
    {
        return self::$undoData[$sender->getName()] ?? null;
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
