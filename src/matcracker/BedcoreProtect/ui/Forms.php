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

namespace matcracker\BedcoreProtect\ui;

use Closure;
use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\config\ConfigParser;
use matcracker\BedcoreProtect\Inspector;
use matcracker\BedcoreProtect\Main;
use matcracker\FormLib\BaseForm;
use matcracker\FormLib\CustomForm;
use matcracker\FormLib\Form;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_keys;
use function is_array;
use function strlen;

final class Forms
{
    private const TYPE_LOOKUP = "lookup";
    private const TYPE_ROLLBACK = "rollback";
    private const TYPE_RESTORE = "restore";

    private ConfigParser $configParser;

    public function __construct(ConfigParser $configParser)
    {
        $this->configParser = $configParser;
    }

    public function getMainMenu(): BaseForm
    {
        $lang = Main::getInstance()->getLanguage();
        return (new Form(
            function (Player $player, $data) use ($lang): void {
                switch ((int)$data) { //Clicked button
                    case 0: //Inspector
                        $player->chat("/bcp inspect");
                        break;
                    case 1: //Near
                        $player->sendForm($this->getNearMenu());
                        break;
                    case 2: //Lookup
                        $player->sendForm($this->getInputMenu(self::TYPE_LOOKUP));
                        break;
                    case 3: //Show
                        if (count(Inspector::getSavedLogs($player)) > 0) {
                            $player->sendForm($this->getShowMenu());
                        } else {
                            $player->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . "&c" . $lang->translateString("command.show.no-logs")));
                        }
                        break;
                    case 4: //Rollback
                        $player->sendForm($this->getInputMenu(self::TYPE_ROLLBACK));
                        break;
                    case 5: //Restore
                        $player->sendForm($this->getInputMenu(self::TYPE_RESTORE));
                        break;
                    case 6: //Undo
                        $player->chat("/bcp undo");
                        break;
                    case 7: //Purge
                        $player->sendForm($this->getPurgeMenu());
                        break;
                    case 8: //Reload
                        $player->chat("/bcp reload");
                        break;
                    case 9: //Status
                        $player->chat("/bcp status");
                        break;
                }
            }
        ))->setMessage($lang->translateString("form.menu.option"))
            ->addClassicButton($lang->translateString("form.menu.inspector"))
            ->addClassicButton($lang->translateString("form.menu.near"))
            ->addClassicButton($lang->translateString("form.menu.lookup"))
            ->addClassicButton($lang->translateString("form.menu.show"))
            ->addClassicButton($lang->translateString("general.rollback"))
            ->addClassicButton($lang->translateString("general.restore"))
            ->addClassicButton($lang->translateString("general.undo"))
            ->addClassicButton($lang->translateString("form.menu.purge"))
            ->addClassicButton($lang->translateString("form.menu.reload"))
            ->addClassicButton($lang->translateString("form.menu.status"))
            ->setTitle(TextFormat::colorize("&3&l" . Main::PLUGIN_NAME . " " . $lang->translateString("form.menu.title")));
    }

    private function getNearMenu(): BaseForm
    {
        $lang = Main::getInstance()->getLanguage();
        return (new CustomForm(
            static function (Player $player, $data): void {
                if (is_array($data)) {
                    $player->chat("/bcp near {$data["radius"]}");
                }
            },
            function (Player $player): void {
                $player->sendForm($this->getMainMenu());
            }
        ))->addSlider($lang->translateString("form.input-menu.radius"), 1, $this->configParser->getMaxRadius(), null, $this->configParser->getDefaultRadius(), "radius")
            ->setTitle(TextFormat::colorize("&3&l" . $lang->translateString("form.menu.near")));
    }

    private function getInputMenu(string $type): BaseForm
    {
        $lang = Main::getInstance()->getLanguage();
        $form = new CustomForm(
            $this->parseForm($type),
            function (Player $player): void {
                $player->sendForm($this->getMainMenu());
            }
        );

        $form->addLabel(TextFormat::BOLD . $lang->translateString("form.input-menu.required-fields"))
            ->addInput($lang->translateString("form.input-menu.time"), "1h3m10s", null, "time")
            ->addLabel(TextFormat::BOLD . $lang->translateString("form.input-menu.optional-fields"));

        if ($type === self::TYPE_LOOKUP) {
            $form->addSlider($lang->translateString("form.input-menu.radius"), 0, $this->configParser->getMaxRadius(), null, null, "radius");
        } else {
            $form->addSlider($lang->translateString("form.input-menu.radius"), 1, $this->configParser->getMaxRadius(), null, $this->configParser->getDefaultRadius(), "radius");
        }

        $form->addInput($lang->translateString("form.input-menu.user-entity"), $lang->translateString("form.input-menu.user-entity-placeholder"), null, "user")
            ->addDropdown($lang->translateString("general.action"), array_keys(CommandParser::$ACTIONS), -1, "action")
            ->addInput($lang->translateString("form.input-menu.restrict-blocks"), "stone,dirt,2:0", null, "inclusions")
            ->addInput($lang->translateString("form.input-menu.exclude-blocks"), "stone,dirt,2:0", null, "exclusions")
            ->setTitle(TextFormat::colorize("&3&l" . $lang->translateString("general.$type")));

        return $form;
    }

    private function parseForm(string $subCmd): Closure
    {
        return static function (Player $player, $data) use ($subCmd): void {
            if (is_array($data)) {
                $time = "t={$data["time"]}";
                $radius = $data["radius"] === 0 ? "" : "r={$data["radius"]}";
                $user = strlen($data["user"]) === 0 ? "" : "\"u={$data["user"]}\"";
                if ($data["action"] !== -1) {
                    $a = array_keys(CommandParser::$ACTIONS)[$data["action"]];
                    $action = "a=$a";
                } else {
                    $action = "";
                }
                $includeBlocks = strlen($data["inclusions"]) === 0 ? "" : "i={$data["inclusions"]}";
                $excludeBlocks = strlen($data["exclusions"]) === 0 ? "" : "e={$data["exclusions"]}";

                $player->chat("/bcp $subCmd $time $radius $user $action $includeBlocks $excludeBlocks");
            }
        };
    }

    private function getShowMenu(): BaseForm
    {
        $lang = Main::getInstance()->getLanguage();
        return (new CustomForm(
            static function (Player $player, $data): void {
                if (is_array($data)) {
                    $player->chat("/bcp show {$data["page"]}:{$data["lines"]}");
                }
            },
            function (Player $player): void {
                $player->sendForm($this->getMainMenu());
            }
        ))->addInput($lang->translateString("form.input-menu.page-number"), "1", "1", "page")
            ->addInput($lang->translateString("form.input-menu.lines-number"), "4", "4", "lines")
            ->setTitle(TextFormat::colorize("&3&l" . $lang->translateString("form.menu.show")));
    }

    private function getPurgeMenu(): BaseForm
    {
        $lang = Main::getInstance()->getLanguage();
        return (new CustomForm(
            static function (Player $player, $data): void {
                if (is_array($data)) {
                    $player->chat("/bcp purge t={$data["time"]}");
                }
            },
            function (Player $player): void {
                $player->sendForm($this->getMainMenu());
            }
        ))->addInput($lang->translateString("form.purge-menu.time"), "1h3m10s", null, "time")
            ->setTitle(TextFormat::colorize("&3&l" . $lang->translateString("form.menu.purge")));
    }
}
