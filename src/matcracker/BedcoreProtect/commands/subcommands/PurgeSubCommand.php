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
use dktapps\pmforms\element\Dropdown;
use dktapps\pmforms\element\Input;
use dktapps\pmforms\element\Toggle;
use matcracker\BedcoreProtect\commands\BCPCommand;
use matcracker\BedcoreProtect\enums\AdditionalParameter;
use matcracker\BedcoreProtect\enums\CommandParameter;
use matcracker\BedcoreProtect\forms\WorldDropDown;
use matcracker\BedcoreProtect\Main;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_merge;
use function in_array;
use function strlen;
use const PHP_FLOAT_MAX;

final class PurgeSubCommand extends ParsableSubCommand
{
    public function onExecute(CommandSender $sender, array $args): void
    {
        $cmdData = $this->parseArguments($sender, $args);
        if ($cmdData !== null) {
            $sender->sendMessage(Main::MESSAGE_PREFIX . $this->getLang()->translateString("subcommand.purge.started"));
            $sender->sendMessage(Main::MESSAGE_PREFIX . $this->getLang()->translateString("subcommand.purge.no-restart"));

            $this->getOwningPlugin()->getDatabase()->getQueryManager()->getPluginQueries()->purge(
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
        /** @var WorldDropDown $worldDropDown */
        $worldDropDown = clone CommandParameter::WORLD()->getFormElement();
        $worldDropDown->setOptions(array_merge(["----"], $worldDropDown->getOptions()));

        $elements = [
            "time" => new Input(
                "time",
                $this->getLang()->translateString("form.purge.delete-data"),
                "1h3m10s"
            ),
            "world" => $worldDropDown,
            "optimize" => new Toggle(
                "optimize",
                $this->getLang()->translateString("form.purge.optimize")
            )
        ];

        return (new CustomForm(
            TextFormat::DARK_AQUA . TextFormat::BOLD . $this->getLang()->translateString("form.menu.purge"),
            $elements,
            function (Player $player, CustomFormResponse $response) use ($elements): void {
                $command = "/bcp {$this->getName()}";

                if (strlen($time = $response->getString("time")) > 0) {
                    $command .= " t=$time";
                }

                if (($worldIdx = $response->getInt("world")) > 0) {
                    /** @var Dropdown $worldDropDown */
                    $worldDropDown = $elements["world"];
                    /** @var string $world */
                    $world = $worldDropDown->getOption($worldIdx);
                    $command .= " w=$world";
                }

                if ($response->getBool("optimize")) {
                    $command .= " #optimize";
                }

                $player->chat($command);
            },
            function (Player $player): void {
                $player->sendForm(BCPCommand::getForm($this->getOwningPlugin(), $player));
            }
        ));
    }

    public function getName(): string
    {
        return "purge";
    }

    public function sendCommandHelp(CommandSender $sender): void
    {
        $sender->sendMessage(TextFormat::DARK_AQUA . "/bcp purge t=<time>" . TextFormat::WHITE . " - " . $this->getLang()->translateString("subcommand.purge.help.description"));
        $sender->sendMessage(TextFormat::ITALIC . TextFormat::GRAY . $this->getLang()->translateString("subcommand.purge.help.example"));
    }
}
