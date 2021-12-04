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
use matcracker\BedcoreProtect\commands\BCPCommand;
use matcracker\BedcoreProtect\enums\CommandParameter;
use matcracker\BedcoreProtect\Main;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
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
        if (isset($args[0])) {
            $sender->sendMessage(TextFormat::WHITE . "----- " . TextFormat::DARK_AQUA . Main::PLUGIN_NAME . " " . $this->getLang()->translateString("subcommand.help.title") . TextFormat::WHITE . " -----");
            if (($subCmd = BCPCommand::getSubCommand(mb_strtolower($args[0]))) !== null) {
                $subCmd->sendCommandHelp($sender);

            } elseif (($param = CommandParameter::fromString($args[0])) !== null) {
                $sender->sendMessage(TextFormat::DARK_AQUA . "/bcp lookup {$param->name()}=<{$param->name()}>" . TextFormat::WHITE . " - " . $this->getLang()->translateString("command.params.{$param->name()}.help", ["lookup"]));
                foreach ($param->getAliases() as $alias) {
                    $sender->sendMessage(TextFormat::DARK_AQUA . "/bcp lookup $alias=<{$param->name()}>" . TextFormat::WHITE . " - " . $this->getLang()->translateString("command.params.generic.help.shortcut"));
                }
                $sender->sendMessage(TextFormat::ITALIC . TextFormat::GRAY . $this->getLang()->translateString("command.params.generic.help.examples", [$param->getExample()]));

            } else {
                $sender->sendMessage(TextFormat::RED . $this->getLang()->translateString("subcommand.help.info-not-found", [$args[0]]));
            }
        } else {
            $this->showGenericHelp($sender);
        }
    }

    private function showGenericHelp(CommandSender $sender): void
    {
        $sender->sendMessage(TextFormat::WHITE . "----- " . TextFormat::DARK_AQUA . Main::PLUGIN_NAME . " " . $this->getLang()->translateString("subcommand.help.title") . TextFormat::WHITE . " -----");
        foreach (BCPCommand::getSubCommands() as $subCommand) {
            if (!$subCommand->testPermission($sender)) {
                continue;
            }

            if (!$sender instanceof Player && $subCommand->isPlayerCommand()) {
                continue;
            }

            $message = TextFormat::DARK_AQUA . "/bcp " . $subCommand->getName();
            if ($subCommand instanceof HelpSubCommand) {
                $message .= TextFormat::DARK_AQUA . " <command>";
            } elseif ($subCommand instanceof ParsableSubCommand) {
                $message .= TextFormat::DARK_AQUA . " <params>";
            }

            $message .= TextFormat::WHITE . " - " . $subCommand->getDescription();
            $sender->sendMessage($message);
        }
        $sender->sendMessage(TextFormat::WHITE . "------");
    }

    public function getName(): string
    {
        return "help";
    }
}
