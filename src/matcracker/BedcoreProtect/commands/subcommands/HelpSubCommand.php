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

namespace matcracker\BedcoreProtect\commands\subcommands;

use dktapps\pmforms\BaseForm;
use matcracker\BedcoreProtect\Main;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use function mb_strtolower;

final class HelpSubCommand extends SubCommand
{
    public function getForm(Player $player): ?BaseForm
    {
        $player->chat("/bcp help");
        return null;
    }

    public function onExecute(CommandSender $sender, array $args): void
    {
        if (isset($args[1])) {
            $this->showCommandHelp($sender, $args[1]);
        } else {
            $this->showGenericHelp($sender);
        }
    }

    private function showCommandHelp(CommandSender $sender, string $subCmd): void
    {
        $subCmd = mb_strtolower($subCmd);
        $sender->sendMessage(TextFormat::colorize("&f----- &3" . Main::PLUGIN_NAME . " &3" . $this->getLang()->translateString("command.help.title") . "&f-----"));
        switch ($subCmd) {
            case "help":
                $sender->sendMessage(TextFormat::colorize("&3/bcp help &f- " . $this->getLang()->translateString("command.help.help2")));
                break;
            case "inspect":
            case "i":
                $sender->sendMessage(TextFormat::colorize("&3" . $this->getLang()->translateString("command.help.inspect1")));
                $sender->sendMessage(TextFormat::colorize("&7* " . $this->getLang()->translateString("command.help.inspect2")));
                $sender->sendMessage(TextFormat::colorize("&7* " . $this->getLang()->translateString("command.help.inspect3")));
                $sender->sendMessage(TextFormat::colorize("&7* " . $this->getLang()->translateString("command.help.inspect4")));
                $sender->sendMessage(TextFormat::colorize("&7* " . $this->getLang()->translateString("command.help.inspect5")));
                $sender->sendMessage(TextFormat::colorize("&7* " . $this->getLang()->translateString("command.help.inspect6")));
                $sender->sendMessage(TextFormat::colorize("&7" . $this->getLang()->translateString("command.help.inspect7")));
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
                $sender->sendMessage(TextFormat::colorize("&3/bcp $subCmd &7<params> &f- " . $this->getLang()->translateString("command.help.parameters1", [$subCmd])));
                $sender->sendMessage(TextFormat::colorize("&3| &7u=<users> &f- " . $this->getLang()->translateString("command.help.parameters2", [$subCmd])));
                $sender->sendMessage(TextFormat::colorize("&3| &7t=<time> &f- " . $this->getLang()->translateString("command.help.parameters3", [$subCmd])));
                $sender->sendMessage(TextFormat::colorize("&3| &7r=<radius> &f- " . $this->getLang()->translateString("command.help.parameters4", [$subCmd])));
                $sender->sendMessage(TextFormat::colorize("&3| &7a=<action> &f- " . $this->getLang()->translateString("command.help.parameters5", [$subCmd])));
                $sender->sendMessage(TextFormat::colorize("&3| &7b=<blocks> &f- " . $this->getLang()->translateString("command.help.parameters6", [$subCmd])));
                $sender->sendMessage(TextFormat::colorize("&3| &7e=<exclude> &f- " . $this->getLang()->translateString("command.help.parameters7", [$subCmd])));
                $sender->sendMessage(TextFormat::colorize("&7" . $this->getLang()->translateString("command.help.parameters8")));
                break;
            case "lookup":
            case "l":
                $sender->sendMessage(TextFormat::colorize("&3/bcp lookup <params>"));
                $sender->sendMessage(TextFormat::colorize("&3/bcp l <params> &f- " . $this->getLang()->translateString("command.help.shortcut")));
                $sender->sendMessage(TextFormat::colorize("&7" . $this->getLang()->translateString("command.help.lookup-more-details")));
                break;
            case "show":
            case "s":
                $sender->sendMessage(TextFormat::colorize("&3/bcp show <page> &f- " . $this->getLang()->translateString("command.help.show-page")));
                $sender->sendMessage(TextFormat::colorize("&3/bcp show <page>:<lines> &f- " . $this->getLang()->translateString("command.help.show-page-lines")));
                break;
            case "purge":
                $sender->sendMessage(TextFormat::colorize("&3/bcp purge t=<time> &f- " . $this->getLang()->translateString("command.help.purge1")));
                $sender->sendMessage(TextFormat::colorize("&7" . $this->getLang()->translateString("command.help.purge2")));
                break;
            case "user":
            case "u":
                $sender->sendMessage(TextFormat::colorize("&3/bcp lookup u=<users> &f- " . $this->getLang()->translateString("command.help.parameters2", [$subCmd])));
                $sender->sendMessage(TextFormat::colorize("&7" . $this->getLang()->translateString("command.help.examples") . ": [u=shoghicp], [u=shoghicp,#zombie]"));
                break;
            case "time":
            case "t":
                $sender->sendMessage(TextFormat::colorize("&3/bcp lookup t=<time> &f- " . $this->getLang()->translateString("command.help.parameters3", [$subCmd])));
                $sender->sendMessage(TextFormat::colorize("&7" . $this->getLang()->translateString("command.help.examples") . ": [t=2w5d7h2m10s], [t=5d2h]."));
                break;
            case "radius":
            case "r":
                $sender->sendMessage(TextFormat::colorize("&3/bcp lookup r=<radius> &f- " . $this->getLang()->translateString("command.help.parameters4", [$subCmd])));
                $sender->sendMessage(TextFormat::colorize("&7" . $this->getLang()->translateString("command.help.examples") . ": [r=10] (" . $this->getLang()->translateString("command.help.radius-example") . ")."));
                break;
            case "action":
            case "a":
                $sender->sendMessage(TextFormat::colorize("&3/bcp lookup a=<action> &f- " . $this->getLang()->translateString("command.help.parameters5", [$subCmd])));
                $sender->sendMessage(TextFormat::colorize("&7" . $this->getLang()->translateString("command.help.examples") . ": [a=block], [a=+block], [a=-block] [a=click], [a=container], [a=kill]"));
                break;
            case "blocks":
            case "b":
                $sender->sendMessage(TextFormat::colorize("&3/bcp lookup b=<blocks> &f- " . $this->getLang()->translateString("command.help.parameters6", [$subCmd])));
                $sender->sendMessage(TextFormat::colorize("&7" . $this->getLang()->translateString("command.help.examples") . ": [b=stone], [b=1,5,stained_glass:8]"));
                $sender->sendMessage(TextFormat::colorize("&7" . $this->getLang()->translateString("command.help.block-names") . ": https://minecraft.gamepedia.com/Block"));
                break;
            case "exclude":
            case "e":
                $sender->sendMessage(TextFormat::colorize("&3/bcp lookup e=<blocks> &f- " . $this->getLang()->translateString("command.help.parameters7", [$subCmd])));
                $sender->sendMessage(TextFormat::colorize("&7" . $this->getLang()->translateString("command.help.examples") . ": [e=stone], [e=1,5,stained_glass:8]"));
                $sender->sendMessage(TextFormat::colorize("&7" . $this->getLang()->translateString("command.help.block-names") . ": https://minecraft.gamepedia.com/Block"));
                break;
            default:
                $sender->sendMessage(TextFormat::colorize($this->getLang()->translateString("command.help.info-not-found", [$subCmd])));
                break;
        }
    }

    private function showGenericHelp(CommandSender $sender): void
    {
        $sender->sendMessage(TextFormat::colorize("&f----- &3" . Main::PLUGIN_NAME . " &3" . $this->getLang()->translateString("command.help.title") . " &f-----"));
        $sender->sendMessage(TextFormat::colorize("&3/bcp help &7<command> &f- " . $this->getLang()->translateString("command.help.help")));
        $sender->sendMessage(TextFormat::colorize("&3/bcp &7inspect &f- " . $this->getLang()->translateString("command.help.inspect")));
        $sender->sendMessage(TextFormat::colorize("&3/bcp &7rollback &3<params> &f- " . $this->getLang()->translateString("command.help.rollback")));
        $sender->sendMessage(TextFormat::colorize("&3/bcp &7restore &3<params> &f- " . $this->getLang()->translateString("command.help.restore")));
        $sender->sendMessage(TextFormat::colorize("&3/bcp &7lookup &3<params> &f- " . $this->getLang()->translateString("command.help.lookup")));
        $sender->sendMessage(TextFormat::colorize("&3/bcp &7show &3<params> &f- " . $this->getLang()->translateString("command.help.show")));
        $sender->sendMessage(TextFormat::colorize("&3/bcp &7purge &3<params> &f- " . $this->getLang()->translateString("command.help.purge")));
        $sender->sendMessage(TextFormat::colorize("&3/bcp &7reload &f- " . $this->getLang()->translateString("command.help.reload")));
        $sender->sendMessage(TextFormat::colorize("&3/bcp &7status &f- " . $this->getLang()->translateString("command.help.status")));
        $sender->sendMessage(TextFormat::colorize("&f------"));
    }

    public function getName(): string
    {
        return "help";
    }
}
