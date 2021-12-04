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

namespace matcracker\BedcoreProtect\forms;

use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use matcracker\BedcoreProtect\commands\subcommands\InspectSubCommand;
use matcracker\BedcoreProtect\commands\subcommands\SubCommand;
use matcracker\BedcoreProtect\Inspector;
use matcracker\BedcoreProtect\Main;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_values;

final class MainMenuForm extends MenuForm
{
    /**
     * MainMenuForm constructor.
     * @param Main $plugin
     * @param Player $player
     * @param SubCommand[] $subCommands
     */
    public function __construct(Main $plugin, Player $player, array $subCommands)
    {
        $menuOptions = [];
        foreach ($subCommands as $key => $subCommand) {
            if ($subCommand->testPermission($player)) {
                if ($subCommand instanceof InspectSubCommand) {
                    $enable = Inspector::isInspector($player) ? "disable" : "enable";
                    $menuOptions[] = new MenuOption($plugin->getLanguage()->translateString("form.menu.inspect.$enable"));
                } else {
                    $menuOptions[] = new MenuOption($plugin->getLanguage()->translateString("form.menu.{$subCommand->getName()}"));
                }
            } else {
                unset($subCommands[$key]);
            }
        }

        $subCommands = array_values($subCommands);

        parent::__construct(
            TextFormat::DARK_AQUA . TextFormat::BOLD . Main::PLUGIN_NAME,
            $plugin->getLanguage()->translateString("form.menu.option"),
            $menuOptions,
            static function (Player $player, int $selectedOption) use ($subCommands): void {
                if (($form = $subCommands[$selectedOption]->getForm($player)) !== null) {
                    $player->sendForm($form);
                }
            }
        );
    }
}
