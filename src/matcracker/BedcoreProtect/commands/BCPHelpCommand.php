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

use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\command\CommandSender;

final class BCPHelpCommand
{

    private function __construct()
    {
    }

    public static function showGenericHelp(CommandSender $sender): void
    {
        $lang = Main::getInstance()->getLanguage();
        $sender->sendMessage(Utils::translateColors("&f----- &3" . Main::PLUGIN_NAME . " &3" . $lang->translateString("command.help.title") . " &f-----"));
        $sender->sendMessage(Utils::translateColors("&3/bcp help &7<command> &f- " . $lang->translateString("command.help.help")));
        $sender->sendMessage(Utils::translateColors("&3/bcp &7menu &f- " . $lang->translateString("command.help.menu")));
        $sender->sendMessage(Utils::translateColors("&3/bcp &7inspect &f- " . $lang->translateString("command.help.inspect")));
        $sender->sendMessage(Utils::translateColors("&3/bcp &7rollback &3<params> &f- " . $lang->translateString("command.help.rollback")));
        $sender->sendMessage(Utils::translateColors("&3/bcp &7restore &3<params> &f- " . $lang->translateString("command.help.restore")));
        $sender->sendMessage(Utils::translateColors("&3/bcp &7lookup &3<params> &f- " . $lang->translateString("command.help.lookup")));
        $sender->sendMessage(Utils::translateColors("&3/bcp &7purge &3<params> &f- " . $lang->translateString("command.help.purge")));
        $sender->sendMessage(Utils::translateColors("&3/bcp &7reload &f- " . $lang->translateString("command.help.reload")));
        $sender->sendMessage(Utils::translateColors("&3/bcp &7status &f- " . $lang->translateString("command.help.status")));
        $sender->sendMessage(Utils::translateColors("&f------"));
    }

    public static function showSpecificHelp(CommandSender $sender, string $subCmd): void
    {
        $lang = Main::getInstance()->getLanguage();
        $subCmd = strtolower($subCmd);
        $sender->sendMessage(Utils::translateColors("&f----- &3" . Main::PLUGIN_NAME . " &3" . $lang->translateString("command.help.title") . "&f-----"));
        switch ($subCmd) {
            case "help":
                $sender->sendMessage(Utils::translateColors("&3/bcp help &f- " . $lang->translateString("command.help.help2")));
                break;
            case "menu":
            case "ui":
                $sender->sendMessage(Utils::translateColors("&3/bcp menu &f- " . $lang->translateString("command.help.menu")));
                break;
            case "inspect":
            case "i":
                $sender->sendMessage(Utils::translateColors("&3" . $lang->translateString("command.help.inspect1")));
                $sender->sendMessage(Utils::translateColors("&7* " . $lang->translateString("command.help.inspect2")));
                $sender->sendMessage(Utils::translateColors("&7* " . $lang->translateString("command.help.inspect3")));
                $sender->sendMessage(Utils::translateColors("&7* " . $lang->translateString("command.help.inspect4")));
                $sender->sendMessage(Utils::translateColors("&7* " . $lang->translateString("command.help.inspect5")));
                $sender->sendMessage(Utils::translateColors("&7* " . $lang->translateString("command.help.inspect6")));
                $sender->sendMessage(Utils::translateColors("&7" . $lang->translateString("command.help.inspect7")));
                break;
            case "rollback":
            case "rb":
            case "restore":
            case "rs":
            case "params":
                if ($subCmd === "params") {
                    $subCmd = "lookup";
                } elseif ($subCmd === "rs") {
                    $subCmd = "restore";
                } elseif ($subCmd === "rb") {
                    $subCmd = "rollback";
                }
                $sender->sendMessage(Utils::translateColors("&3/bcp {$subCmd} &7<params> &f- " . $lang->translateString("command.help.parameters1", [$subCmd])));
                $sender->sendMessage(Utils::translateColors("&3| &7u=<users> &f- " . $lang->translateString("command.help.parameters2", [$subCmd])));
                $sender->sendMessage(Utils::translateColors("&3| &7t=<time> &f- " . $lang->translateString("command.help.parameters3", [$subCmd])));
                $sender->sendMessage(Utils::translateColors("&3| &7r=<radius> &f- " . $lang->translateString("command.help.parameters4", [$subCmd])));
                $sender->sendMessage(Utils::translateColors("&3| &7a=<action> &f- " . $lang->translateString("command.help.parameters5", [$subCmd])));
                $sender->sendMessage(Utils::translateColors("&3| &7b=<blocks> &f- " . $lang->translateString("command.help.parameters6", [$subCmd])));
                $sender->sendMessage(Utils::translateColors("&3| &7e=<exclude> &f- " . $lang->translateString("command.help.parameters7", [$subCmd])));
                $sender->sendMessage(Utils::translateColors("&7" . $lang->translateString("command.help.parameters8")));
                break;
            case "lookup":
            case "l":
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup <params>"));
                $sender->sendMessage(Utils::translateColors("&3/bcp l <params> &f- " . $lang->translateString("command.help.shortcut")));
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup <page> &f- " . $lang->translateString("command.help.lookup1")));
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup <page>:<lines> &f- " . $lang->translateString("command.help.lookup2")));
                $sender->sendMessage(Utils::translateColors("&7" . $lang->translateString("command.help.lookup3")));
                break;
            case "purge":
                $sender->sendMessage(Utils::translateColors("&3/bcp purge t=<time> &f- " . $lang->translateString("command.help.purge1")));
                $sender->sendMessage(Utils::translateColors("&7" . $lang->translateString("command.help.purge2")));
                break;
            case "user":
            case "u":
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup u=<users> &f- " . $lang->translateString("command.help.parameters2", [$subCmd])));
                $sender->sendMessage(Utils::translateColors("&7" . $lang->translateString("command.help.examples") . ": [u=shoghicp], [u=shoghicp,#zombie]"));
                break;
            case "time":
            case "t":
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup t=<time> &f- " . $lang->translateString("command.help.parameters3", [$subCmd])));
                $sender->sendMessage(Utils::translateColors("&7" . $lang->translateString("command.help.examples") . ": [t=2w5d7h2m10s], [t=5d2h]."));
                break;
            case "radius":
            case "r":
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup r=<radius> &f- " . $lang->translateString("command.help.parameters4", [$subCmd])));
                $sender->sendMessage(Utils::translateColors("&7" . $lang->translateString("command.help.examples") . ": [r=10] (" . $lang->translateString("command.help.radius-example") . ")."));
                break;
            case "action":
            case "a":
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup a=<action> &f- " . $lang->translateString("command.help.parameters5", [$subCmd])));
                $sender->sendMessage(Utils::translateColors("&7" . $lang->translateString("command.help.examples") . ": [a=block], [a=+block], [a=-block] [a=click], [a=container], [a=kill]"));
                break;
            case "blocks":
            case "b":
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup b=<blocks> &f- " . $lang->translateString("command.help.parameters6", [$subCmd])));
                $sender->sendMessage(Utils::translateColors("&7" . $lang->translateString("command.help.examples") . ": [b=stone], [b=1,5,stained_glass:8]"));
                $sender->sendMessage(Utils::translateColors("&7" . $lang->translateString("command.help.block-names") . ": https://minecraft.gamepedia.com/Block"));
                break;
            case "exclude":
            case "e":
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup e=<blocks> &f- " . $lang->translateString("command.help.parameters7", [$subCmd])));
                $sender->sendMessage(Utils::translateColors("&7" . $lang->translateString("command.help.examples") . ": [e=stone], [e=1,5,stained_glass:8]"));
                $sender->sendMessage(Utils::translateColors("&7" . $lang->translateString("command.help.block-names") . ": https://minecraft.gamepedia.com/Block"));
                break;
            default:
                $sender->sendMessage(Utils::translateColors($lang->translateString("command.help.info-not-found", [$subCmd])));
                break;
        }
    }
}