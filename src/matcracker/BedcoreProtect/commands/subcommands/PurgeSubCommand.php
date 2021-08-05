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
use dktapps\pmforms\element\Input;
use matcracker\BedcoreProtect\commands\BCPCommand;
use matcracker\BedcoreProtect\enums\AdditionalParameter;
use matcracker\BedcoreProtect\Main;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use function in_array;
use const PHP_FLOAT_MAX;

final class PurgeSubCommand extends ParsableSubCommand
{
    public function onExecute(CommandSender $sender, array $args): void
    {
        $cmdData = $this->parseArguments($sender, $args);
        if ($cmdData !== null) {
            $sender->sendMessage(Main::MESSAGE_PREFIX . $this->getLang()->translateString("subcommand.purge.started"));
            $sender->sendMessage(Main::MESSAGE_PREFIX . $this->getLang()->translateString("subcommand.purge.no-restart"));

            $this->getPlugin()->getDatabase()->getQueryManager()->getPluginQueries()->purge(
                (float)$cmdData->getTime() ?? PHP_FLOAT_MAX,
                $cmdData->getWorld(),
                in_array(AdditionalParameter::OPTIMIZE(), $cmdData->getAdditionalParams()),
                function (int $affectedRows) use ($sender): void {
                    $sender->sendMessage(Main::MESSAGE_PREFIX . $this->getLang()->translateString("subcommand.purge.success"));
                    $sender->sendMessage(Main::MESSAGE_PREFIX . $this->getLang()->translateString("subcommand.purge.deleted-rows", [$affectedRows]));
                }
            );
        }
    }

    public function getForm(Player $player): ?BaseForm
    {
        return (new CustomForm(
            TextFormat::DARK_AQUA . TextFormat::BOLD . $this->getLang()->translateString("form.menu.purge"),
            [
                new Input(
                    "time",
                    $this->getLang()->translateString("form.purge.delete-data"),
                    "1h3m10s"
                )
            ],
            static function (Player $player, CustomFormResponse $response): void {
                $player->chat("/bcp purge t={$response->getString("time")}");
            },
            function (Player $player): void {
                $player->sendForm(BCPCommand::getForm($this->getPlugin(), $player));
            }
        ));
    }

    public function sendCommandHelp(CommandSender $sender): void
    {
        $sender->sendMessage(TextFormat::DARK_AQUA . "/bcp purge t=<time>" . TextFormat::WHITE . " - " . $this->getLang()->translateString("subcommand.purge.help.description"));
        $sender->sendMessage(TextFormat::ITALIC . TextFormat::GRAY . $this->getLang()->translateString("subcommand.purge.help.example"));
    }

    public function getName(): string
    {
        return "purge";
    }
}
