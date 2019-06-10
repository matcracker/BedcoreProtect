<?php

/*
 * BedcoreProtect
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

use Carbon\Carbon;
use Carbon\CarbonInterface;
use matcracker\BedcoreProtect\storage\QueriesConst;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\item\ItemFactory;
use pocketmine\Player;

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
        self::$inspectors[self::getUUID($inspector)]["enabled"] = true;
    }

    private static function getUUID(CommandSender $sender): string
    {
        $uuid = $sender->getServer()->getServerUniqueId();
        if ($sender instanceof Player) {
            $uuid = $sender->getUniqueId();
        }

        return $uuid->toString();
    }

    /**
     * It removes a player from the inspector mode. It returns the success of operation.
     *
     * @param CommandSender $inspector
     * @return bool
     */
    public static function removeInspector(CommandSender $inspector): bool
    {
        if (!self::isInspector($inspector)) return false;

        unset(self::$inspectors[self::getUUID($inspector)]);

        return true;
    }

    /**
     * It checks if a player is an inspector.
     *
     * @param CommandSender $inspector
     * @return bool
     */
    public static function isInspector(CommandSender $inspector): bool
    {
        if (!self::issetInspector($inspector)) return false;

        return self::$inspectors[self::getUUID($inspector)]["enabled"];
    }

    private static function issetInspector(CommandSender $inspector): bool
    {
        if (isset(self::$inspectors[self::getUUID($inspector)])) {
            return array_key_exists("enabled", self::$inspectors[self::getUUID($inspector)]);
        }
        return false;
    }

    public static function cacheLogs(CommandSender $inspector, array $logs = []): void
    {
        self::$inspectors[self::getUUID($inspector)]["logs"] = $logs;
    }

    /**
     * @param CommandSender $inspector
     * @return array
     */
    public static function getCachedLogs(CommandSender $inspector): array
    {
        return self::$inspectors[self::getUUID($inspector)]["logs"];
    }

    public static function clearCache(): void
    {
        self::$inspectors = [];
    }

    /**
     * It sends a message to the inspector with all the log's info.
     *
     * @param CommandSender $inspector
     * @param int $page
     * @param array $logs
     */
    public static function parseLogs(CommandSender $inspector, array $logs, int $page = 0): void
    {
        if (count($logs) > 0) {
            $chunkLogs = array_chunk($logs, 4);
            $maxPages = count($chunkLogs) - 1;
            if (isset($chunkLogs[$page])) {
                $inspector->sendMessage(Utils::translateColors("&f-----&3 " . Main::PLUGIN_NAME . " &7(Page $page/$maxPages) &f-----"));
                foreach ($chunkLogs[$page] as $log) {
                    //Default
                    $entityFromName = (string)$log['entity_from'];
                    $x = (int)$log['x'];
                    $y = (int)$log['y'];
                    $z = (int)$log['z'];
                    $worldName = (string)$log['world_name'];
                    $action = (int)$log['action'];
                    $rollback = (bool)$log['rollback'];

                    $actionName = Utils::getActionName($action); //Convert action to string
                    $time = $log['time'];
                    $timeStamp = Carbon::createFromTimestamp(is_int($time) ? $time : strtotime($time));

                    $midMessage = "";

                    if ($action >= QueriesConst::PLACED && $action <= QueriesConst::CLICKED) {
                        $blockFound = $action === QueriesConst::BROKE ? "old" : "new";
                        $id = (int)$log["{$blockFound}_block_id"];
                        $damage = (int)$log["{$blockFound}_block_damage"];
                        $blockName = (string)$log["{$blockFound}_block_name"];

                        $midMessage = "#$id:$damage ($blockName)";
                    } elseif ($action === QueriesConst::KILLED) {
                        $entityToName = $log['entity_to'];

                        $midMessage = $entityToName;
                    } elseif ($action === QueriesConst::ADDED || $action === QueriesConst::REMOVED) {
                        $itemFound = $action === QueriesConst::REMOVED ? "old" : "new";
                        $id = (int)$log["{$itemFound}_item_id"];
                        $damage = (int)$log["{$itemFound}_item_damage"];
                        $amount = (int)$log["{$itemFound}_amount"];
                        $itemName = ItemFactory::get($id, $damage)->getName();
                        $midMessage = "$amount x #$id:$damage ($itemName)";
                    }

                    $inspector->sendMessage(Utils::translateColors(($rollback ? "&o" : "") . "&7" .
                        $timeStamp->ago(null, true, 2, CarbonInterface::JUST_NOW) .
                        "&f - &3$entityFromName &f$actionName &3" . $midMessage . " &f - &7(x$x/y$y/z$z/$worldName)&f."));
                }
            } else {
                $inspector->sendMessage(Utils::translateColors("&3" . Main::PLUGIN_NAME . "&f - &cThe page &6$page&c does not exist!"));
            }
        } else {
            $inspector->sendMessage(Utils::translateColors("&3" . Main::PLUGIN_NAME . "&f - &cNo block data found for this location."));
        }
    }

}
