<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
 *
 * Copyright (C) 2019-2023
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
use Generator;
use matcracker\BedcoreProtect\Main;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;
use function implode;

final class StatusSubCommand extends SubCommand
{
    public function getForm(Player $player): ?BaseForm
    {
        $player->chat("/bcp status");
        return null;
    }

    public function onExecute(CommandSender $sender, array $args): void
    {
        Await::f2c(
            function () use ($sender): Generator {
                $description = $this->getOwningPlugin()->getDescription();
                $pluginVersion = $description->getVersion();
                /** @var array $status */
                [$status] = yield from $this->getOwningPlugin()->getDatabase()->getStatus();
                $dbVersion = (string)$status["version"];
                $initDbVersion = (string)$status["init_version"];

                if ($dbVersion !== $initDbVersion) {
                    //Database version could be minor respect the plugin, in this case I apply a BC suffix (Backward Compatibility)
                    $dbVersion .= TextFormat::GRAY . " (" . $this->getLang()->translateString("subcommand.status.initial-database-version", [$initDbVersion]) . ")";
                }

                $sender->sendMessage(TextFormat::WHITE . "----- " . TextFormat::DARK_AQUA . Main::PLUGIN_NAME . " ----- ");
                $sender->sendMessage(TextFormat::DARK_AQUA . $this->getLang()->translateString("subcommand.status.version", [TextFormat::WHITE . $pluginVersion]));
                $sender->sendMessage(TextFormat::DARK_AQUA . $this->getLang()->translateString("subcommand.status.database-connection", [TextFormat::WHITE . $this->getOwningPlugin()->getParsedConfig()->getPrintableDatabaseType()]));
                $sender->sendMessage(TextFormat::DARK_AQUA . $this->getLang()->translateString("subcommand.status.database-version", [TextFormat::WHITE . $dbVersion]));
                $sender->sendMessage(TextFormat::DARK_AQUA . $this->getLang()->translateString("subcommand.status.author", [TextFormat::WHITE . implode(", ", $description->getAuthors())]));
                $sender->sendMessage(TextFormat::DARK_AQUA . $this->getLang()->translateString("subcommand.status.website", [TextFormat::WHITE . $description->getWebsite()]));
            }
        );
    }

    public function getName(): string
    {
        return "status";
    }
}
