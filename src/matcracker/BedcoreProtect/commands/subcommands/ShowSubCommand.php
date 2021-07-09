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
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\LookupData;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use function explode;

final class ShowSubCommand extends SubCommand
{
    public function getForm(Player $player): ?BaseForm
    {
        return (new CustomForm(
            TextFormat::DARK_BLUE . TextFormat::BOLD . $this->getPlugin()->getLanguage()->translateString("form.menu.show"),
            [
                new Input(
                    "page",
                    $this->getLang()->translateString("form.input-menu.page-number"),
                    "1",
                    "1"
                ),
                new Input(
                    "lines",
                    $this->getLang()->translateString("form.input-menu.lines-number"),
                    "4",
                    "4"
                )
            ],
            static function (Player $player, CustomFormResponse $response): void {
                $player->chat("/bcp show {$response->getString("page")}:{$response->getString("lines")}");
            },
            function (Player $player): void {
                $player->sendForm(BCPCommand::getForm($this->getPlugin(), $player));
            }
        ));
    }

    public function onExecute(CommandSender $sender, array $args): void
    {
        if (!isset($args[0])) {
            $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("command.error.one-parameter"));
            return;
        }

        $data = LookupData::getData($sender);
        if ($data === null) {
            $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("command.error.no-data"));

            return;
        }

        $split = explode(":", $args[0]);
        //Check if the input page is a number
        if (!ctype_digit($split[0])) {
            $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("command.error.no-numeric-value"));
            return;
        }

        $page = (int)$split[0];

        if (isset($split[1])) {
            if (!ctype_digit($split[1])) {
                $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("command.error.no-numeric-value"));
                return;
            }
            $lines = (int)$split[1];
        } else {
            //Default
            $lines = 4;
        }

        $offset = ($page - 1) * $lines;
        $rows = $data->getRows();

        if ($offset > $rows || $page <= 0) {
            $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("inspector.page-not-exist", [$page]));
            return;
        }

        $pluginQueries = $this->getPlugin()->getDatabase()->getQueryManager()->getPluginQueries();
        switch ($data->getQueryType()) {
            case LookupData::NEAR_LOG:
                if ($sender instanceof Player) {
                    $pluginQueries->requestNearLog($sender, $data->getCommandData()->getRadius(), $lines, $offset);
                }
                break;
            case LookupData::BLOCK_LOG:
                if ($sender instanceof Player) {
                    $pluginQueries->requestBlockLog($sender, $data->getPosition(), $data->getCommandData()->getRadius(), $lines, $offset);
                }
                break;
            case LookupData::TRANSACTION_LOG:
                if ($sender instanceof Player) {
                    $pluginQueries->requestTransactionLog($sender, $data->getPosition(), $data->getCommandData()->getRadius(), $lines, $offset);
                }
                break;
            case LookupData::LOOKUP_LOG:
                $pluginQueries->requestLookup($sender, $data->getCommandData(), $data->getPosition(), $lines, $offset);
                break;
        }
    }

    public function getName(): string
    {
        return "show";
    }
}
