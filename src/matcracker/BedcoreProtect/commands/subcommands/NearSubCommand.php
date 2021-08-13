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
use dktapps\pmforms\CustomForm;
use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\element\Slider;
use matcracker\BedcoreProtect\commands\BCPCommand;
use matcracker\BedcoreProtect\Main;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

final class NearSubCommand extends SubCommand
{
    public function getForm(Player $player): ?BaseForm
    {
        return (new CustomForm(
            TextFormat::DARK_AQUA . TextFormat::BOLD . $this->getLang()->translateString("form.menu.near"),
            [
                new Slider(
                    "radius",
                    $this->getLang()->translateString("form.params.radius"),
                    1,
                    $this->getOwningPlugin()->getParsedConfig()->getMaxRadius(),
                    1.0,
                    $this->getOwningPlugin()->getParsedConfig()->getDefaultRadius(),
                ),
            ],
            static function (Player $player, CustomFormResponse $response): void {
                $radius = (int)$response->getFloat("radius");
                $player->chat("/bcp near $radius");
            },
            function (Player $player): void {
                $player->sendForm(BCPCommand::getForm($this->getOwningPlugin(), $player));
            }
        ));
    }

    /**
     * @param Player $sender
     * @param array $args
     */
    public function onExecute(CommandSender $sender, array $args): void
    {
        if (isset($args[0])) {
            if (!ctype_digit($args[0])) {
                $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("subcommand.error.no-numeric-value"));
                return;
            }

            $near = (int)$args[0];
            $maxRadius = $this->getOwningPlugin()->getParsedConfig()->getMaxRadius();
            if ($near < 0 || $near > $maxRadius) {
                $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("subcommand.near.out-of-range", [0, $maxRadius]));
                return;
            }
        } else {
            $near = $this->getOwningPlugin()->getParsedConfig()->getDefaultRadius();
        }

        $this->getOwningPlugin()->getDatabase()->getQueryManager()->getPluginQueries()->requestNearLog($sender, $near);
    }

    public function isPlayerCommand(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return "near";
    }
}
