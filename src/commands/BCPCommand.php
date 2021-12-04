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

namespace matcracker\BedcoreProtect\commands;

use matcracker\BedcoreProtect\commands\subcommands\HelpSubCommand;
use matcracker\BedcoreProtect\commands\subcommands\InspectSubCommand;
use matcracker\BedcoreProtect\commands\subcommands\LookupSubCommand;
use matcracker\BedcoreProtect\commands\subcommands\NearSubCommand;
use matcracker\BedcoreProtect\commands\subcommands\PurgeSubCommand;
use matcracker\BedcoreProtect\commands\subcommands\ReloadSubCommand;
use matcracker\BedcoreProtect\commands\subcommands\RestoreSubCommand;
use matcracker\BedcoreProtect\commands\subcommands\RollbackSubCommand;
use matcracker\BedcoreProtect\commands\subcommands\ShowSubCommand;
use matcracker\BedcoreProtect\commands\subcommands\StatusSubCommand;
use matcracker\BedcoreProtect\commands\subcommands\SubCommand;
use matcracker\BedcoreProtect\commands\subcommands\UndoSubCommand;
use matcracker\BedcoreProtect\enums\CommandParameter;
use matcracker\BedcoreProtect\forms\MainMenuForm;
use matcracker\BedcoreProtect\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\utils\TextFormat;
use function array_shift;
use function count;
use function mb_strtolower;
use function strlen;

final class BCPCommand extends Command implements PluginOwned
{
    /** @var SubCommand[] */
    private static array $subCommands = [];
    /** @var SubCommand[] */
    private static array $subCommandsAlias = [];

    public function __construct(private Main $plugin)
    {
        parent::__construct("bedcoreprotect", aliases: ["core", "co", "bcp"]);
        $this->setPermission("bcp.command.bedcoreprotect");
        $this->updateTranslations();

        $this->loadSubCommand(new HelpSubCommand($plugin));
        $this->loadSubCommand(new InspectSubCommand($plugin));
        $this->loadSubCommand(new LookupSubCommand($plugin, [CommandParameter::TIME(), CommandParameter::WORLD()]));
        $this->loadSubCommand(new NearSubCommand($plugin));
        $this->loadSubCommand(new PurgeSubCommand($plugin, [CommandParameter::TIME()]));
        $this->loadSubCommand(new ReloadSubCommand($plugin));
        $this->loadSubCommand(new RestoreSubCommand($plugin, [CommandParameter::TIME(), CommandParameter::RADIUS(), CommandParameter::WORLD()]));
        $this->loadSubCommand(new RollbackSubCommand($plugin, [CommandParameter::TIME(), CommandParameter::RADIUS(), CommandParameter::WORLD()]));
        $this->loadSubCommand(new ShowSubCommand($plugin));
        $this->loadSubCommand(new StatusSubCommand($plugin));
        $this->loadSubCommand(new UndoSubCommand($plugin));
    }

    public function updateTranslations(): void
    {
        $this->setUsage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getOwningPlugin()->getLanguage()->translateString("command.bcp.usage"));
        $this->setDescription($this->getOwningPlugin()->getLanguage()->translateString("command.bcp.description"));
        $this->setPermissionMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getOwningPlugin()->getLanguage()->translateString("command.bcp.no-permission"));
    }

    public function getOwningPlugin(): Main
    {
        return $this->plugin;
    }

    private function loadSubCommand(SubCommand $subCommand): void
    {
        self::$subCommands[$subCommand->getName()] = $subCommand;
        if (strlen($subCommand->getAlias()) > 0) {
            self::$subCommandsAlias[$subCommand->getAlias()] = $subCommand;
        }
    }

    public static function getSubCommand(string $name): ?SubCommand
    {
        return self::$subCommands[$name] ?? self::$subCommandsAlias[$name] ?? null;
    }

    /**
     * @return SubCommand[]
     */
    public static function getSubCommands(): array
    {
        return self::$subCommands;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (!$this->getOwningPlugin()->isEnabled()) {
            return true;
        }

        if (!$this->testPermission($sender)) {
            return true;
        }

        if (!isset($args[0]) && $sender instanceof Player && $this->getOwningPlugin()->getParsedConfig()->isEnabledUI()) {
            $sender->sendForm(self::getForm($this->getOwningPlugin(), $sender));
            return true;
        }

        if (count($args) === 0) {
            $sender->sendMessage($this->getUsage());
            return true;
        }

        $subCommandName = mb_strtolower(array_shift($args));

        if (isset(self::$subCommands[$subCommandName])) {
            $subCommand = self::$subCommands[$subCommandName];
        } elseif (isset(self::$subCommandsAlias[$subCommandName])) {
            $subCommand = self::$subCommandsAlias[$subCommandName];
        } else {
            $sender->sendMessage($this->getUsage());
            return true;
        }

        $subCommand->execute($sender, $args);

        return true;
    }

    public static function getForm(Main $plugin, Player $player): MainMenuForm
    {
        return new MainMenuForm($plugin, $player, self::$subCommands);
    }
}
