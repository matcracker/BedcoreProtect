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

namespace matcracker\BedcoreProtect;

use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\block\BlockFactory;
use pocketmine\command\CommandSender;
use pocketmine\item\ItemFactory;
use pocketmine\Player;
use UnexpectedValueException;

final class Inspector
{
    private static $inspectors = [];

    private function __construct()
    {
    }

    /**
     * It adds a player into the inspector mode. It returns the success of operation.
     *
     * @param CommandSender $inspector
     */
    public static function addInspector(CommandSender $inspector): void
    {
        self::$inspectors[self::getSenderUUID($inspector)]["enabled"] = true;
    }

    private static function getSenderUUID(CommandSender $sender): string
    {
        return ($sender instanceof Player ? $sender->getUniqueId() : $sender->getServer()->getServerUniqueId())->toString();
    }

    /**
     * It removes a player from the inspector mode. It returns the success of operation.
     *
     * @param CommandSender $inspector
     *
     * @return bool
     */
    public static function removeInspector(CommandSender $inspector): bool
    {
        if (!self::isInspector($inspector)) return false;

        unset(self::$inspectors[self::getSenderUUID($inspector)]);

        return true;
    }

    /**
     * It checks if a player is an inspector.
     *
     * @param CommandSender $inspector
     *
     * @return bool
     */
    public static function isInspector(CommandSender $inspector): bool
    {
        return self::$inspectors[self::getSenderUUID($inspector)]['enabled'] ?? false;
    }

    public static function cacheLogs(CommandSender $inspector, array $logs = []): void
    {
        self::$inspectors[self::getSenderUUID($inspector)]['logs'] = $logs;
    }

    /**
     * @param CommandSender $inspector
     *
     * @return array
     */
    public static function getCachedLogs(CommandSender $inspector): array
    {
        return self::$inspectors[self::getSenderUUID($inspector)]['logs'] ?? [];
    }

    public static function clearCache(): void
    {
        self::$inspectors = [];
    }

    /**
     * It sends a message to the inspector with all the log's info.
     *
     * @param CommandSender $inspector
     * @param array $logs
     * @param int $page
     * @param int $lines
     */
    public static function parseLogs(CommandSender $inspector, array $logs, int $page = 0, int $lines = 4): void
    {
        $lang = Main::getInstance()->getLanguage();
        if (empty($logs)) {
            $inspector->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . '&c' . $lang->translateString('inspector.no-data')));

            return;
        }

        if ($lines < 1) {
            $inspector->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . '&c' . $lang->translateString('inspector.more-lines')));

            return;
        }

        $chunkLogs = array_chunk($logs, $lines);
        $maxPages = count($chunkLogs);
        $fakePage = $page + 1;
        if (!isset($chunkLogs[$page])) {
            $inspector->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . '&c' . $lang->translateString('inspector.page-not-exist', [$fakePage])));

            return;
        }

        $inspector->sendMessage(Utils::translateColors('&f-----&3 ' . Main::PLUGIN_NAME . ' &7(' . $lang->translateString('inspector.page', [$fakePage, $maxPages]) . ') &f-----'));
        foreach ($chunkLogs[$page] as $log) {
            //Default
            $from = (string)$log['entity_from'];
            $x = (int)$log['x'];
            $y = (int)$log['y'];
            $z = (int)$log['z'];
            $worldName = (string)$log['world_name'];
            $action = Action::fromType((int)$log['action']);
            $rollback = (bool)$log['rollback'];

            $timeStamp = (is_int($log['time']) ? (int)$log['time'] : strtotime($log['time']));

            $typeColumn = ($action->equals(Action::BREAK()) || $action->equals(Action::REMOVE())) ? 'old' : 'new';
            if (isset($log["{$typeColumn}_id"], $log["{$typeColumn}_meta"])) {
                $id = (int)$log["{$typeColumn}_id"];
                $meta = (int)$log["{$typeColumn}_meta"];
                if (isset($log["{$typeColumn}_amount"])) {
                    $amount = (int)$log["{$typeColumn}_amount"];

                    $itemName = ItemFactory::get($id, $meta)->getName();
                    $to = "{$amount} x #{$id}:{$meta} ({$itemName})";
                } else {
                    $blockName = BlockFactory::get($id, $meta)->getName();
                    $to = "#{$id}:{$meta} ({$blockName})";
                }
            } elseif (isset($log['entity_to'])) {
                $to = "#{$log['entity_to']}";
            } else {
                throw new UnexpectedValueException('Unexpected log parsed. Is your database up to date?');
            }

            //TODO: Use strikethrough (&m) when MC fix it.
            $inspector->sendMessage(Utils::translateColors(($rollback ? '&o' : '') . '&7' . Utils::timeAgo($timeStamp)
                . "&f - &3{$from} &f{$action->getMessage()} &3{$to} &f - &7(x{$x}/y{$y}/z{$z}/{$worldName})&f."));
        }
        $inspector->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $lang->translateString('inspector.view-old-data') . ' /bcp l <page>:<lines>.'));

    }

}
