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

namespace matcracker\BedcoreProtect\storage\queries;

use Generator;
use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\math\Area;
use matcracker\BedcoreProtect\utils\ConfigParser;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Await;
use function microtime;
use function round;
use function strtolower;
use function time;

abstract class Query
{
    /** @var DataConnector */
    protected $connector;
    /** @var ConfigParser */
    protected $configParser;

    public function __construct(DataConnector $connector, ConfigParser $configParser)
    {
        $this->connector = $connector;
        $this->configParser = $configParser;
    }

    public function rollback(Area $area, CommandParser $commandParser, ?callable $onComplete = null): void
    {
        $this->rawRollback(true, $area, $commandParser, $onComplete);
    }

    protected function rawRollback(bool $rollback, Area $area, CommandParser $commandParser, ?callable $onComplete = null): void
    {
        Await::f2c(
            function () use ($rollback, $area, $commandParser) {
                $startTime = microtime(true);

                /** @var int[][] $logRows */
                $logRows = yield $this->getRollbackLogIds($rollback, $area, $commandParser);

                /** @var int[] $logIds */
                $logIds = [];
                foreach ($logRows as $logRow) {
                    $logIds[] = (int)$logRow['log_id'];
                }

                $changes = yield $this->onRollback($rollback, $area, $commandParser, $logIds);

                yield $this->updateRollbackStatus($rollback, $logIds);

                $this->sendRollbackReport($rollback, $area, $commandParser, $startTime, $changes);
            },
            $onComplete
        );
    }

    final protected function getRollbackLogIds(bool $rollback, Area $area, CommandParser $commandParser): Generator
    {
        return $this->executeRawSelect($commandParser->buildLogsSelectionQuery($rollback, $area->getBoundingBox()));
    }

    final protected function executeRawSelect(string $query, array $args = []): Generator
    {
        $this->connector->executeSelectRaw($query, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    /**
     * @param bool $rollback
     * @param Area $area
     * @param CommandParser $commandParser
     * @param int[] $logIds
     * @return Generator
     */
    abstract protected function onRollback(bool $rollback, Area $area, CommandParser $commandParser, array $logIds): Generator;

    /**
     * @param bool $rollback
     * @param int[] $logIds
     * @return Generator
     */
    private function updateRollbackStatus(bool $rollback, array $logIds): Generator
    {
        $this->connector->executeChange(QueriesConst::UPDATE_ROLLBACK_STATUS, [
            'rollback' => $rollback,
            'log_ids' => $logIds
        ], yield, yield Await::REJECT);

        return yield Await::ONCE;
    }

    private function sendRollbackReport(bool $rollback, Area $area, CommandParser $commandParser, float $startTime, int $changes): void
    {
        $duration = round(microtime(true) - $startTime, 2);
        if (($sender = Server::getInstance()->getPlayer($commandParser->getSenderName())) !== null) {
            $date = Utils::timeAgo(time() - $commandParser->getTime());
            $lang = Main::getInstance()->getLanguage();

            $sender->sendMessage(TextFormat::colorize('&f------'));
            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString(($rollback ? 'rollback' : 'restore') . '.completed', [$area->getWorld()->getName()])));
            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString(($rollback ? 'rollback' : 'restore') . '.date', [$date])));
            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('rollback.radius', [$commandParser->getRadius()])));

            $this->onRollbackComplete($sender, $area, $commandParser, $changes);
            /*
            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('rollback.blocks', [$blocks])));
            if ($items > 0) {
                $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('rollback.items', [$items])));
            }
            if ($entities > 0) {
                $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('rollback.entities', [$entities])));
            }
            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('rollback.modified-chunks', [$chunks])));*/
            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('rollback.time-taken', [$duration])));
            $sender->sendMessage(TextFormat::colorize('&f------'));
        }
    }

    abstract protected function onRollbackComplete(Player $player, Area $area, CommandParser $commandParser, int $changes): void;

    public function restore(Area $area, CommandParser $commandParser, ?callable $onComplete = null): void
    {
        $this->rawRollback(false, $area, $commandParser, $onComplete);
    }

    final protected function addRawLog(string $uuid, Position $position, Action $action): void
    {
        $this->connector->executeInsert(QueriesConst::ADD_HISTORY_LOG, [
            'uuid' => strtolower($uuid),
            'x' => $position->getFloorX(),
            'y' => $position->getFloorY(),
            'z' => $position->getFloorZ(),
            'world_name' => $position->getLevel()->getName(),
            'action' => $action->getType()
        ]);
    }

    final protected function getLastLogId(): Generator
    {
        return $this->executeSelect(QueriesConst::GET_LAST_LOG_ID);
    }

    final protected function executeSelect(string $query, array $args = []): Generator
    {
        $this->connector->executeSelect($query, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }
}
