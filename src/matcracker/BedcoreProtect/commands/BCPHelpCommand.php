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
        $sender->sendMessage(Utils::translateColors("&3/bcp &7purge &3<params> &f-" . $lang->translateString("command.help.purge")));
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
                $sender->sendMessage(Utils::translateColors("&3/bcp help &f- Displays a list of all commands."));
                break;
            case "menu":
            case "ui":
                $sender->sendMessage(Utils::translateColors("&3/bcp menu &f- Allows to use commands with a graphic interface."));
                break;
            case "inspect":
            case "i":
                $sender->sendMessage(Utils::translateColors("&3With the inspector enabled, you can do the following:"));
                $sender->sendMessage(Utils::translateColors("&7* Left-click a block to see who placed that block."));
                $sender->sendMessage(Utils::translateColors("&7* Right-click a block to see what adjacent block was removed."));
                $sender->sendMessage(Utils::translateColors("&7* Place a block to see what block was removed at the location."));
                $sender->sendMessage(Utils::translateColors("&7* Place a block in liquid (etc) to see who placed it."));
                $sender->sendMessage(Utils::translateColors("&7* Right-click on a door, chest, etc, to see who last used it."));
                $sender->sendMessage(Utils::translateColors("&7Tip: You can use just &3\"/bcp i\"&7 for quicker access."));
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
                $sender->sendMessage(Utils::translateColors("&3/bcp {$subCmd} &7<params> &f- Perform the {$subCmd}."));
                $sender->sendMessage(Utils::translateColors("&3| &7u=<users> &f- Specify the user(s) to {$subCmd}."));
                $sender->sendMessage(Utils::translateColors("&3| &7t=<time> &f- Specify the amount of time to {$subCmd}."));
                $sender->sendMessage(Utils::translateColors("&3| &7r=<radius> &f- Specify a radius area to limit the {$subCmd} to."));
                $sender->sendMessage(Utils::translateColors("&3| &7a=<action> &f- Restrict the {$subCmd} to a certain action."));
                $sender->sendMessage(Utils::translateColors("&3| &7b=<blocks> &f- Restrict the {$subCmd} to certain block types."));
                $sender->sendMessage(Utils::translateColors("&3| &7e=<exclude> &f- Exclude blocks/users from the {$subCmd}."));
                $sender->sendMessage(Utils::translateColors("&7Please see &3\"/bcp help <param>\"&7 for detailed parameter info."));
                break;
            case "lookup":
            case "l":
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup <params>"));
                $sender->sendMessage(Utils::translateColors("&3/bcp l <params> &f- Command shortcut."));
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup <page> &f- Use after inspecting a block to view different logs pages."));
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup <page>:<lines> &f- Use after inspecting a block to view more lines of logs in a page."));
                $sender->sendMessage(Utils::translateColors("&7Please see \"/bcp help params\" for detailed parameters."));
                break;
            case "purge":
                $sender->sendMessage(Utils::translateColors("&3/bcp purge t=<time> &f- Delete data older than specified time."));
                $sender->sendMessage(Utils::translateColors("&7For example, \"/bcp purge t:30d\" will delete all data older than one month, and only keep the last 30 days of data."));
                break;
            case "user":
            case "u":
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup u=<users> &f- Specify the user(s) to lookup."));
                $sender->sendMessage(Utils::translateColors("&7Examples: [u=shoghicp], [u=shoghicp,#zombie]"));
                break;
            case "time":
            case "t":
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup t=<time> &f- Specify the amount of time to lookup."));
                $sender->sendMessage(Utils::translateColors("&7Examples: [t=2w5d7h2m10s], [t=5d2h]."));
                break;
            case "radius":
            case "r":
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup r=<radius> &f- Specify a radius area."));
                $sender->sendMessage(Utils::translateColors("&7Examples: [r=10] (Only make changes within 10 blocks of you)."));
                break;
            case "action":
            case "a":
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup a=<action> &f- Restrict the lookup to a certain action."));
                $sender->sendMessage(Utils::translateColors("&7Examples: [a=block], [a=+block], [a=-block] [a=click], [a=container], [a=kill]"));
                break;
            case "blocks":
            case "b":
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup b=<blocks> &f- Restrict the lookup to certain blocks."));
                $sender->sendMessage(Utils::translateColors("&7Examples: [b=stone], [b=1,5,stained_glass:8]"));
                $sender->sendMessage(Utils::translateColors("&7Block Names: https://minecraft.gamepedia.com/Block"));
                break;
            case "exclude":
            case "e":
                $sender->sendMessage(Utils::translateColors("&3/bcp lookup e=<blocks> &f- Exclude blocks."));
                $sender->sendMessage(Utils::translateColors("&7Examples: [e=stone], [e=1,5,stained_glass:8]"));
                $sender->sendMessage(Utils::translateColors("&7Block Names: https://minecraft.gamepedia.com/Block"));
                break;
            default:
                $sender->sendMessage(Utils::translateColors("Information for command \"/bcp help {$subCmd}\" not found."));
                break;
        }
    }
}