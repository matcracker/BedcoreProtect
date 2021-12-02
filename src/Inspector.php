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

namespace matcracker\BedcoreProtect;

use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\BlockFactory;
use pocketmine\command\CommandSender;
use pocketmine\item\ItemFactory;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function count;
use function intdiv;

final class Inspector
{
    /** @var bool[] */
    private static array $inspectors = [];

    private function __construct()
    {
        //NOOP
    }

    /**
     * It adds a player into the inspector mode. It returns the success of operation.
     *
     * @param Player $inspector
     */
    public static function addInspector(Player $inspector): void
    {
        self::$inspectors[Utils::getSenderUUID($inspector)] = true;
    }

    /**
     * It removes a player from the inspector mode. It returns the success of operation.
     *
     * @param Player $inspector
     *
     * @return bool
     */
    public static function removeInspector(Player $inspector): bool
    {
        if (!self::isInspector($inspector)) {
            return false;
        }

        unset(self::$inspectors[Utils::getSenderUUID($inspector)]);

        return true;
    }

    /**
     * It checks if a player is an inspector.
     *
     * @param Player $inspector
     *
     * @return bool
     */
    public static function isInspector(Player $inspector): bool
    {
        return isset(self::$inspectors[Utils::getSenderUUID($inspector)]);
    }

    public static function removeAll(): void
    {
        self::$inspectors = [];
    }


    /**
     * It sends a message to the inspector with all the log's info.
     *
     * @param CommandSender $inspector
     * @param array $logs
     * @param int $limit
     * @param int $offset
     */
    public static function sendLogReport(CommandSender $inspector, array $logs, int $limit, int $offset): void
    {
        $lang = Main::getInstance()->getLanguage();
        if (count($logs) === 0) {
            $inspector->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $lang->translateString("subcommand.show.empty-data"));
            return;
        }

        if ($limit <= 0) {
            $inspector->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $lang->translateString("subcommand.show.too-few-lines"));
            return;
        }

        //Default
        $rows = (int)$logs[0]["cnt_rows"];

        $page = intdiv($offset, $limit) + 1;
        $pages = intdiv($rows, $limit);

        if ($rows % $limit !== 0) {
            $pages++;
        }

        $inspector->sendMessage(TextFormat::WHITE . "----- " . TextFormat::DARK_AQUA . Main::PLUGIN_NAME . " " . TextFormat::GRAY . "(" . $lang->translateString("subcommand.show.page", [$page, $pages]) . ")" . TextFormat::WHITE . " -----");

        foreach ($logs as $log) {
            $from = (string)$log["entity_from"];
            $x = (int)$log["x"];
            $y = (int)$log["y"];
            $z = (int)$log["z"];
            $worldName = (string)$log["world_name"];
            $action = Action::fromType((int)$log["action"]);
            $rollback = (bool)$log["rollback"];

            $timeStamp = Utils::timeAgo((int)$log["time"]);

            $prefix = ($action->equals(Action::BREAK()) || $action->equals(Action::REMOVE())) ? "old" : "new";

            if (isset($log["{$prefix}_id"])) {
                $id = (int)$log["{$prefix}_id"];
                $meta = (int)$log["{$prefix}_meta"];
                if (isset($log["{$prefix}_amount"])) {
                    $amount = (int)$log["{$prefix}_amount"];

                    $itemName = ItemFactory::getInstance()->get($id, $meta)->getVanillaName();
                    $to = "$itemName (x$amount)";
                } else {
                    $blockName = BlockFactory::getInstance()->get($id, $meta)->getName();
                    $to = "$blockName (#$id:$meta)";
                }
            } elseif (isset($log["entity_to"])) {
                $to = $log["entity_to"];
            } else {
                $inspector->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $lang->translateString("subcommand.show.corrupted-data"));
                return;
            }

            //TODO: Use strikethrough (&m) when MC fix it (https://bugs.mojang.com/browse/MCPE-41729).
            $inspector->sendMessage(($rollback ? TextFormat::ITALIC : "") . TextFormat::GRAY . $timeStamp . TextFormat::WHITE . " - " .
                TextFormat::DARK_AQUA . "$from " . TextFormat::WHITE . "{$action->getMessage()} " . TextFormat::DARK_AQUA . "$to " . TextFormat::WHITE . " - " .
                TextFormat::GRAY . "(x$x/y$y/z$z/$worldName)" . TextFormat::WHITE . ".");
        }

        $inspector->sendMessage(TextFormat::WHITE . $lang->translateString("subcommand.show.view-old-data"));
    }
}
