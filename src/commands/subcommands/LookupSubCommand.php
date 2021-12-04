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

use matcracker\BedcoreProtect\enums\CommandParameter;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class LookupSubCommand extends ParsableSubCommand
{
    public function onExecute(CommandSender $sender, array $args): void
    {
        if ($sender instanceof Player) {
            $pos = $sender->getPosition();
            $default = [
                CommandParameter::WORLD()->name() => $pos->getWorld()->getFolderName()
            ];
        } else {
            $pos = null;
            $default = [];
        }

        $cmdData = $this->parseArguments($sender, $args, $default);
        if ($cmdData !== null) {
            $this->getOwningPlugin()->getDatabase()->getQueryManager()->getPluginQueries()->requestLookup($sender, $cmdData, $pos);
        }
    }

    public function getName(): string
    {
        return "lookup";
    }

    public function getAlias(): string
    {
        return "l";
    }
}
