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

use Generator;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\enums\ActionType;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\utils\EntityUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\World\World;
use poggit\libasynql\DataConnector;
use function microtime;

class PlayerQueries extends Query
{

    public function __construct(
        protected Main            $plugin,
        protected DataConnector   $connector,
        protected EntitiesQueries $entitiesQueries
    )
    {
        parent::__construct($plugin, $connector);
    }

    private function addSession(Player $player, Action $action): Generator
    {
        $position = $player->getPosition();

        yield from $this->entitiesQueries->addEntity($player);

        yield from $this->addRawLog(
            EntityUtils::getUniqueId($player),
            $position->floor(),
            $position->getWorld()->getFolderName(),
            $action,
            microtime(true)
        );
    }

    public function addSessionJoin(Player $player): Generator
    {
        yield from $this->addSession($player, ActionType::SESSION_JOIN());
    }

    public function addSessionLeft(Player $player): Generator
    {
        yield from $this->addSession($player, ActionType::SESSION_LEFT());
    }

    public function addMessage(Player $player, string $message, Action $action): Generator
    {
        $position = $player->getPosition();

        yield from $this->entitiesQueries->addEntity($player);

        /** @var $lastId int */
        [$lastId] = yield from $this->addRawLog(
            EntityUtils::getUniqueId($player),
            $position->floor(),
            $position->getWorld()->getFolderName(),
            $action,
            microtime(true)
        );

        return yield from $this->connector->asyncInsert(QueriesConst::ADD_CHAT_LOG, [
            "log_id" => $lastId,
            "message" => $message
        ]);
    }

    public function onRollback(CommandSender $sender, World $world, bool $rollback, array $logIds): Generator
    {
        0 && yield;
    }
}