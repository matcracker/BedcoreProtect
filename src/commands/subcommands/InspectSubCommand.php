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
use matcracker\BedcoreProtect\Inspector;
use matcracker\BedcoreProtect\Main;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

final class InspectSubCommand extends SubCommand
{
    public function getForm(Player $player): ?BaseForm
    {
        $player->chat("/bcp inspect");
        return null;
    }

    /**
     * @param Player $sender
     * @param string[] $args
     */
    public function onExecute(CommandSender $sender, array $args): void
    {
        if (Inspector::isInspector($sender)) {
            Inspector::removeInspector($sender);
            $sender->sendMessage(Main::MESSAGE_PREFIX . $this->getLang()->translateString("subcommand.inspect.disabled"));
        } else {
            Inspector::addInspector($sender);
            $sender->sendMessage(Main::MESSAGE_PREFIX . $this->getLang()->translateString("subcommand.inspect.enabled"));
        }
    }

    public function isPlayerCommand(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return "inspect";
    }

    public function sendCommandHelp(CommandSender $sender): void
    {
        $sender->sendMessage(TextFormat::DARK_AQUA . $this->getLang()->translateString("subcommand.inspect.help.1"));
        $sender->sendMessage(TextFormat::WHITE . "* " . $this->getLang()->translateString("subcommand.inspect.help.2"));
        $sender->sendMessage(TextFormat::WHITE . "* " . $this->getLang()->translateString("subcommand.inspect.help.3"));
        $sender->sendMessage(TextFormat::WHITE . "* " . $this->getLang()->translateString("subcommand.inspect.help.4"));
        $sender->sendMessage(TextFormat::WHITE . "* " . $this->getLang()->translateString("subcommand.inspect.help.5"));
        $sender->sendMessage(TextFormat::WHITE . "* " . $this->getLang()->translateString("subcommand.inspect.help.6"));
        $sender->sendMessage(TextFormat::ITALIC . TextFormat::GRAY . $this->getLang()->translateString("subcommand.inspect.help.7", ["/bcp {$this->getAlias()}"]));
    }

    public function getAlias(): string
    {
        return "i";
    }
}
