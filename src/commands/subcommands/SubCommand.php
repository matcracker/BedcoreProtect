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
use pocketmine\lang\Language;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\utils\TextFormat;
use function strlen;

abstract class SubCommand implements PluginOwned
{
    public function __construct(private Main $plugin)
    {
    }

    abstract public function getForm(Player $player): ?BaseForm;

    public function execute(CommandSender $sender, array $args): void
    {
        if (!$this->testPermission($sender)) {
            $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getOwningPlugin()->getLanguage()->translateString("command.bcp.no-permission"));
            return;
        }

        if ($this->isPlayerCommand() && !$sender instanceof Player) {
            $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getOwningPlugin()->getLanguage()->translateString("command.error.no-console"));
            return;
        }

        if ($this instanceof ParsableSubCommand && !isset($args[0])) {
            $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getOwningPlugin()->getLanguage()->translateString("subcommand.error.one-parameter"));
            return;
        }

        $this->onExecute($sender, $args);
    }

    public function testPermission(CommandSender $sender): bool
    {
        return $sender->hasPermission("bcp.subcommand.{$this->getName()}");
    }

    abstract public function getName(): string;

    public function getOwningPlugin(): Main
    {
        return $this->plugin;
    }

    public function isPlayerCommand(): bool
    {
        return false;
    }

    /**
     * @param CommandSender $sender
     * @param string[] $args
     */
    abstract public function onExecute(CommandSender $sender, array $args): void;

    public function getDescription(): string
    {
        return $this->getLang()->translateString("subcommand.{$this->getName()}.description");
    }

    final protected function getLang(): Language
    {
        return $this->plugin->getLanguage();
    }

    public function sendCommandHelp(CommandSender $sender): void
    {
        $sender->sendMessage(TextFormat::DARK_AQUA . "/bcp " . TextFormat::GRAY . $this->getName() . TextFormat::WHITE . " - " . $this->getLang()->translateString("subcommand.{$this->getName()}.description"));
        if (strlen($this->getAlias()) > 0) {
            $sender->sendMessage(TextFormat::DARK_AQUA . "/bcp " . TextFormat::GRAY . $this->getAlias() . TextFormat::WHITE . " - " . $this->getLang()->translateString("command.bcp.help.shortcut"));
        }
    }

    public function getAlias(): string
    {
        return "";
    }

}