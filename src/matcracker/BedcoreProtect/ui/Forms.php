<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
 *
 * Copyright (C) 2019
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

namespace matcracker\BedcoreProtect\ui;

use Closure;
use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\utils\ConfigParser;
use matcracker\BedcoreProtect\utils\Utils;
use matcracker\FormLib\BaseForm;
use matcracker\FormLib\CustomForm;
use matcracker\FormLib\Form;
use pocketmine\Player;

final class Forms
{
    /**@var ConfigParser $configParser */
    private $configParser;

    public function __construct(ConfigParser $configParser)
    {
        $this->configParser = $configParser;
    }

    public function getMainMenu(): BaseForm
    {
        return (new Form(
            function (Player $player, $data) {
                switch ((int)$data) { //Clicked button
                    case 0: //Inspector
                        $player->chat("/bcp inspect");
                        break;
                    case 1: //Rollback
                        $player->sendForm($this->getInputMenu("rollback"));
                        break;
                    case 2: //Restore
                        $player->sendForm($this->getInputMenu("restore"));
                        break;
                    case 3: //Lookup
                        $player->sendForm($this->getInputMenu("lookup"));
                        break;
                    case 4: //Purge
                        $player->sendForm($this->getPurgeMenu());
                        break;
                    case 5: //Reload
                        $player->chat("/bcp reload");
                        break;
                    case 6: //Status
                        $player->chat("/bcp status");
                        break;
                }
            }
        ))->setMessage("Select an option:")
            ->addClassicButton("Enable/Disable inspector mode")
            ->addClassicButton("Rollback")
            ->addClassicButton("Restore")
            ->addClassicButton("Lookup data")
            ->addClassicButton("Purge data")
            ->addClassicButton("Reload plugin")
            ->addClassicButton("Show plugin status")
            ->setTitle(Utils::translateColors("&3&lBedcoreProtect Main Menu"));
    }

    private function getPurgeMenu(): BaseForm
    {
        return (new CustomForm(
            function (Player $player, $data) {
                if (is_array($data)) {
                    $player->chat("/bcp purge t={$data[0]}");
                }
            }
        ))->addInput("Time to delete data", "1h3m10s")
            ->setTitle(Utils::translateColors("&3&lPurge"));
    }

    private function getInputMenu(string $type): BaseForm
    {
        return (new CustomForm($this->parseForm($type)))
            ->addLabel("Required fields:")
            ->addInput("Time", "1h3m10s")
            ->addSlider("Radius", 1, $this->configParser->getMaxRadius(), null, $this->configParser->getDefaultRadius())
            ->addLabel("Optional fields:")
            ->addInput("User/Entity name", "Insert player name or #entity")
            ->addDropdown("Action", array_keys(CommandParser::$ACTIONS), -1)
            ->addInput("Restrict blocks (accepts ID:meta)", "stone,dirt,2:0")
            ->addInput("Exclude blocks (accepts ID:meta)", "stone,dirt,2:0")
            ->setTitle(Utils::translateColors("&3&l" . ucfirst($type)));
    }

    private function parseForm(string $subCmd): Closure
    {
        return function (Player $player, $data) use ($subCmd) {
            if (is_array($data)) {
                $time = "t={$data[1]}";
                $radius = "r={$data[2]}";
                $user = empty($data[4]) ? "" : "u={$data[4]}";
                $action = "";
                if ($data[5] !== -1) {
                    $a = array_keys(CommandParser::$ACTIONS)[$data[5]];
                    $action = "a={$a}";
                }
                $includeBlocks = empty($data[6]) ? "" : "b={$data[6]}";
                $excludeBlocks = empty($data[7]) ? "" : "e={$data[7]}";
                $player->chat("/bcp {$subCmd} {$time} {$radius} {$user} {$action} {$includeBlocks} {$excludeBlocks}");
            }
        };
    }
}