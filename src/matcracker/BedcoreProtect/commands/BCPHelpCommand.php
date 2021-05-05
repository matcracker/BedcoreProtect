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
use pocketmine\command\CommandSender;
use pocketmine\lang\BaseLang;
use pocketmine\utils\TextFormat;
use function mb_strtolower;

final class BCPHelpCommand
{
    /** @var CommandSender */
    private $sender;
    /** @var BaseLang */
    private $lang;

    public function __construct(CommandSender $sender, BaseLang $lang)
    {
        $this->sender = $sender;
        $this->lang = $lang;
    }

    public function showGenericHelp(): void
    {
        $this->sender->sendMessage(TextFormat::colorize("&f----- &3" . Main::PLUGIN_NAME . " &3" . $this->lang->translateString("command.help.title") . " &f-----"));
        $this->sender->sendMessage(TextFormat::colorize("&3/bcp help &7<command> &f- " . $this->lang->translateString("command.help.help")));
        $this->sender->sendMessage(TextFormat::colorize("&3/bcp &7menu &f- " . $this->lang->translateString("command.help.menu")));
        $this->sender->sendMessage(TextFormat::colorize("&3/bcp &7inspect &f- " . $this->lang->translateString("command.help.inspect")));
        $this->sender->sendMessage(TextFormat::colorize("&3/bcp &7rollback &3<params> &f- " . $this->lang->translateString("command.help.rollback")));
        $this->sender->sendMessage(TextFormat::colorize("&3/bcp &7restore &3<params> &f- " . $this->lang->translateString("command.help.restore")));
        $this->sender->sendMessage(TextFormat::colorize("&3/bcp &7lookup &3<params> &f- " . $this->lang->translateString("command.help.lookup")));
        $this->sender->sendMessage(TextFormat::colorize("&3/bcp &7show &3<params> &f- " . $this->lang->translateString("command.help.show")));
        $this->sender->sendMessage(TextFormat::colorize("&3/bcp &7purge &3<params> &f- " . $this->lang->translateString("command.help.purge")));
        $this->sender->sendMessage(TextFormat::colorize("&3/bcp &7reload &f- " . $this->lang->translateString("command.help.reload")));
        $this->sender->sendMessage(TextFormat::colorize("&3/bcp &7status &f- " . $this->lang->translateString("command.help.status")));
        $this->sender->sendMessage(TextFormat::colorize("&f------"));
    }

    public function showCommandHelp(string $subCmd): void
    {
        $subCmd = mb_strtolower($subCmd);
        $this->sender->sendMessage(TextFormat::colorize("&f----- &3" . Main::PLUGIN_NAME . " &3" . $this->lang->translateString("command.help.title") . "&f-----"));
        switch ($subCmd) {
            case "help":
                $this->sender->sendMessage(TextFormat::colorize("&3/bcp help &f- " . $this->lang->translateString("command.help.help2")));
                break;
            case "menu":
            case "ui":
                $this->sender->sendMessage(TextFormat::colorize("&3/bcp menu &f- " . $this->lang->translateString("command.help.menu")));
                break;
            case "inspect":
            case "i":
                $this->sender->sendMessage(TextFormat::colorize("&3" . $this->lang->translateString("command.help.inspect1")));
                $this->sender->sendMessage(TextFormat::colorize("&7* " . $this->lang->translateString("command.help.inspect2")));
                $this->sender->sendMessage(TextFormat::colorize("&7* " . $this->lang->translateString("command.help.inspect3")));
                $this->sender->sendMessage(TextFormat::colorize("&7* " . $this->lang->translateString("command.help.inspect4")));
                $this->sender->sendMessage(TextFormat::colorize("&7* " . $this->lang->translateString("command.help.inspect5")));
                $this->sender->sendMessage(TextFormat::colorize("&7* " . $this->lang->translateString("command.help.inspect6")));
                $this->sender->sendMessage(TextFormat::colorize("&7" . $this->lang->translateString("command.help.inspect7")));
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
                $this->sender->sendMessage(TextFormat::colorize("&3/bcp {$subCmd} &7<params> &f- " . $this->lang->translateString("command.help.parameters1", [$subCmd])));
                $this->sender->sendMessage(TextFormat::colorize("&3| &7u=<users> &f- " . $this->lang->translateString("command.help.parameters2", [$subCmd])));
                $this->sender->sendMessage(TextFormat::colorize("&3| &7t=<time> &f- " . $this->lang->translateString("command.help.parameters3", [$subCmd])));
                $this->sender->sendMessage(TextFormat::colorize("&3| &7r=<radius> &f- " . $this->lang->translateString("command.help.parameters4", [$subCmd])));
                $this->sender->sendMessage(TextFormat::colorize("&3| &7a=<action> &f- " . $this->lang->translateString("command.help.parameters5", [$subCmd])));
                $this->sender->sendMessage(TextFormat::colorize("&3| &7b=<blocks> &f- " . $this->lang->translateString("command.help.parameters6", [$subCmd])));
                $this->sender->sendMessage(TextFormat::colorize("&3| &7e=<exclude> &f- " . $this->lang->translateString("command.help.parameters7", [$subCmd])));
                $this->sender->sendMessage(TextFormat::colorize("&7" . $this->lang->translateString("command.help.parameters8")));
                break;
            case "lookup":
            case "l":
                $this->sender->sendMessage(TextFormat::colorize("&3/bcp lookup <params>"));
                $this->sender->sendMessage(TextFormat::colorize("&3/bcp l <params> &f- " . $this->lang->translateString("command.help.shortcut")));
                $this->sender->sendMessage(TextFormat::colorize("&7" . $this->lang->translateString("command.help.lookup-more-details")));
                break;
            case "show":
            case "s":
                $this->sender->sendMessage(TextFormat::colorize("&3/bcp show <page> &f- " . $this->lang->translateString("command.help.show-page")));
                $this->sender->sendMessage(TextFormat::colorize("&3/bcp show <page>:<lines> &f- " . $this->lang->translateString("command.help.show-page-lines")));
                break;
            case "purge":
                $this->sender->sendMessage(TextFormat::colorize("&3/bcp purge t=<time> &f- " . $this->lang->translateString("command.help.purge1")));
                $this->sender->sendMessage(TextFormat::colorize("&7" . $this->lang->translateString("command.help.purge2")));
                break;
            case "user":
            case "u":
                $this->sender->sendMessage(TextFormat::colorize("&3/bcp lookup u=<users> &f- " . $this->lang->translateString("command.help.parameters2", [$subCmd])));
                $this->sender->sendMessage(TextFormat::colorize("&7" . $this->lang->translateString("command.help.examples") . ": [u=shoghicp], [u=shoghicp,#zombie]"));
                break;
            case "time":
            case "t":
                $this->sender->sendMessage(TextFormat::colorize("&3/bcp lookup t=<time> &f- " . $this->lang->translateString("command.help.parameters3", [$subCmd])));
                $this->sender->sendMessage(TextFormat::colorize("&7" . $this->lang->translateString("command.help.examples") . ": [t=2w5d7h2m10s], [t=5d2h]."));
                break;
            case "radius":
            case "r":
                $this->sender->sendMessage(TextFormat::colorize("&3/bcp lookup r=<radius> &f- " . $this->lang->translateString("command.help.parameters4", [$subCmd])));
                $this->sender->sendMessage(TextFormat::colorize("&7" . $this->lang->translateString("command.help.examples") . ": [r=10] (" . $this->lang->translateString("command.help.radius-example") . ")."));
                break;
            case "action":
            case "a":
                $this->sender->sendMessage(TextFormat::colorize("&3/bcp lookup a=<action> &f- " . $this->lang->translateString("command.help.parameters5", [$subCmd])));
                $this->sender->sendMessage(TextFormat::colorize("&7" . $this->lang->translateString("command.help.examples") . ": [a=block], [a=+block], [a=-block] [a=click], [a=container], [a=kill]"));
                break;
            case "blocks":
            case "b":
                $this->sender->sendMessage(TextFormat::colorize("&3/bcp lookup b=<blocks> &f- " . $this->lang->translateString("command.help.parameters6", [$subCmd])));
                $this->sender->sendMessage(TextFormat::colorize("&7" . $this->lang->translateString("command.help.examples") . ": [b=stone], [b=1,5,stained_glass:8]"));
                $this->sender->sendMessage(TextFormat::colorize("&7" . $this->lang->translateString("command.help.block-names") . ": https://minecraft.gamepedia.com/Block"));
                break;
            case "exclude":
            case "e":
                $this->sender->sendMessage(TextFormat::colorize("&3/bcp lookup e=<blocks> &f- " . $this->lang->translateString("command.help.parameters7", [$subCmd])));
                $this->sender->sendMessage(TextFormat::colorize("&7" . $this->lang->translateString("command.help.examples") . ": [e=stone], [e=1,5,stained_glass:8]"));
                $this->sender->sendMessage(TextFormat::colorize("&7" . $this->lang->translateString("command.help.block-names") . ": https://minecraft.gamepedia.com/Block"));
                break;
            default:
                $this->sender->sendMessage(TextFormat::colorize($this->lang->translateString("command.help.info-not-found", [$subCmd])));
                break;
        }
    }
}
