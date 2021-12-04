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
use dktapps\pmforms\element\CustomFormElement;
use dktapps\pmforms\element\Dropdown;
use dktapps\pmforms\element\Label;
use dktapps\pmforms\element\Toggle;
use InvalidArgumentException;
use matcracker\BedcoreProtect\commands\BCPCommand;
use matcracker\BedcoreProtect\commands\CommandData;
use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\enums\AdditionalParameter;
use matcracker\BedcoreProtect\enums\CommandParameter;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\utils\Utils;
use matcracker\BedcoreProtect\utils\WorldUtils;
use pocketmine\command\CommandSender;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use function array_diff;
use function array_intersect;
use function array_keys;
use function array_map;
use function array_unique;
use function count;
use function explode;
use function implode;
use function in_array;
use function mb_strtolower;
use function mb_substr;
use function strlen;

abstract class ParsableSubCommand extends SubCommand
{
    /** @var string[] */
    private array $requiredParamsNames;

    /**
     * @param Main $plugin
     * @param CommandParameter[] $requiredParams
     */
    public function __construct(Main $plugin, private array $requiredParams)
    {
        parent::__construct($plugin);
        $this->requiredParamsNames = array_map(static function (CommandParameter $parameter): string {
            return $parameter->name();
        }, $this->requiredParams);
    }

    public function getForm(Player $player): ?BaseForm
    {
        /** @var CustomFormElement[] $elements */
        $elements = [];
        if (count($this->requiredParams) > 0) {
            $elements["required-fields"] = new Label(
                "required-fields",
                TextFormat::BOLD . $this->getLang()->translateString("form.params.required-fields")
            );
            foreach ($this->requiredParams as $requiredParam) {
                if ($requiredParam->equals(CommandParameter::RADIUS())) {
                    $elements["global-radius"] = new Toggle(
                        "global-radius",
                        $this->getLang()->translateString("form.params.global-radius")
                    );
                }
                $elements[$requiredParam->name()] = $requiredParam->getFormElement();
            }
        }

        $optionalParams = array_diff(CommandParameter::getAll(), $this->requiredParams);

        $elements["optional-fields"] = new Label(
            "optional-fields",
            TextFormat::BOLD . $this->getLang()->translateString("form.params.optional-fields")
        );
        foreach ($optionalParams as $optionalParam) {
            if ($optionalParam->equals(CommandParameter::RADIUS())) {
                $elements["global-radius"] = new Toggle(
                    "global-radius",
                    $this->getLang()->translateString("form.params.global-radius")
                );
            }
            $elements[$optionalParam->name()] = $optionalParam->getFormElement();
        }

        return (new CustomForm(
            TextFormat::DARK_AQUA . TextFormat::BOLD . $this->getOwningPlugin()->getLanguage()->translateString("form.menu.{$this->getName()}"),
            $elements,
            function (Player $player, CustomFormResponse $response) use ($elements): void {
                $command = "/bcp {$this->getName()}";

                if (strlen($time = $response->getString("time")) > 0) {
                    $command .= " t=$time";
                }

                if ($response->getBool("global-radius")) {
                    $command .= " r=#global";
                } else {
                    if (($radius = (int)$response->getFloat("radius")) > 0) {
                        $command .= " r=$radius";
                    }
                }

                /** @var Dropdown $worldDropDown */
                $worldDropDown = $elements["world"];
                /** @var string $world */
                $world = $worldDropDown->getOption($response->getInt("world"));
                $command .= " w=$world";

                if (strlen($users = $response->getString("users")) > 0) {
                    $command .= " u=$users";
                }

                if (strlen($inclusions = $response->getString("inclusions")) > 0) {
                    $command .= " i=$inclusions";
                }

                if (strlen($exclusions = $response->getString("exclusions")) > 0) {
                    $command .= " e=$exclusions";
                }

                $player->chat($command);
            },
            function (Player $player): void {
                $player->sendForm(BCPCommand::getForm($this->getOwningPlugin(), $player));
            }
        ));
    }

    public function sendCommandHelp(CommandSender $sender): void
    {
        $sender->sendMessage(TextFormat::DARK_AQUA . "/bcp " . $this->getName() . TextFormat::GRAY . " <params>" . TextFormat::WHITE . " - " . $this->getLang()->translateString("subcommand.{$this->getName()}.description"));
        if (strlen($this->getAlias()) > 0) {
            $sender->sendMessage(TextFormat::DARK_AQUA . "/bcp " . $this->getAlias() . TextFormat::GRAY . " <params>" . TextFormat::WHITE . " - " . $this->getLang()->translateString("command.bcp.help.shortcut"));
        }
        $sender->sendMessage(TextFormat::DARK_AQUA . "| " . TextFormat::WHITE . $this->getLang()->translateString("command.params.generic.help.parameters"));
        $sender->sendMessage(TextFormat::DARK_AQUA . "| " . TextFormat::GRAY . "u=<users> " . TextFormat::WHITE . "- " . $this->getLang()->translateString("command.params.users.help", [$this->getName()]));
        $sender->sendMessage(TextFormat::DARK_AQUA . "| " . TextFormat::GRAY . "t=<time> " . TextFormat::WHITE . "- " . $this->getLang()->translateString("command.params.time.help", [$this->getName()]));
        $sender->sendMessage(TextFormat::DARK_AQUA . "| " . TextFormat::GRAY . "w=<world_name> " . TextFormat::WHITE . "- " . $this->getLang()->translateString("command.params.world.help", [$this->getName()]));
        $sender->sendMessage(TextFormat::DARK_AQUA . "| " . TextFormat::GRAY . "r=<radius> " . TextFormat::WHITE . "- " . $this->getLang()->translateString("command.params.radius.help", [$this->getName()]));
        $sender->sendMessage(TextFormat::DARK_AQUA . "| " . TextFormat::GRAY . "a=<action> " . TextFormat::WHITE . "- " . $this->getLang()->translateString("command.params.actions.help", [$this->getName()]));
        $sender->sendMessage(TextFormat::DARK_AQUA . "| " . TextFormat::GRAY . "i=<include> " . TextFormat::WHITE . "- " . $this->getLang()->translateString("command.params.include.help", [$this->getName()]));
        $sender->sendMessage(TextFormat::DARK_AQUA . "| " . TextFormat::GRAY . "e=<exclude> " . TextFormat::WHITE . "- " . $this->getLang()->translateString("command.params.exclude.help", [$this->getName()]));
        $sender->sendMessage(TextFormat::ITALIC . TextFormat::GRAY . $this->getLang()->translateString("command.params.generic.help.extra", ["/bcp help <param>"]));

    }

    /**
     * @param CommandSender $sender
     * @param array<int, string> $args
     * @param array<string, string> $defaultValues
     * @return CommandData|null
     */
    final protected function parseArguments(CommandSender $sender, array $args, array $defaultValues = []): ?CommandData
    {
        $MAX_PARAMS = CommandParameter::count();
        if (count($args) > $MAX_PARAMS) {
            $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("parser.too-many-parameters", [$MAX_PARAMS]));

            return null;
        }

        $argMap = $defaultValues;
        $additionalParams = [];
        //Counter to check if all the required parameters are present.
        $currReqParams = count($argMap);

        foreach ($args as $arg) {
            //Check if the argument is an additional parameter by checking the first char.
            if ($arg[0] === AdditionalParameter::KEY_CHAR) {
                try {
                    $additionalParams[] = AdditionalParameter::fromString(mb_substr($arg, 1));
                    continue;
                } catch (InvalidArgumentException) {
                    $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("parser.invalid-additional-parameter", [mb_strtolower($arg)]));
                    return null;
                }
            }

            $argData = explode("=", $arg);
            if (count($argData) !== 2) {
                $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("parser.invalid-parameter-syntax"));

                return null;
            }

            $parameter = CommandParameter::fromString($argData[0]);
            if ($parameter === null) {
                $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("parser.invalid-parameter", [implode(", ", CommandParameter::getAllNames())]));
                return null;
            }

            if (strlen($argData[1]) === 0) {
                $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("parser.empty-parameter", [$argData[1]]));
                return null;
            }

            if (in_array($parameter, $this->requiredParams)) {
                $currReqParams++;
            }

            $argMap[$parameter->name()] = $argData[1];
        }

        if (count(array_intersect($userParams = array_keys($argMap), $this->requiredParamsNames)) !== count($this->requiredParamsNames)) {
            $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("parser.missing-parameters", [implode(", ", array_diff($this->requiredParamsNames, $userParams))]));
            return null;
        }

        $users = $time = $radius = $world = $actions = $inclusions = $exclusions = null;

        /**
         * @var string $parameter
         * @var string $value
         */
        foreach ($argMap as $parameter => $value) {
            switch ($parameter) {
                case CommandParameter::USERS()->name():
                    $users = array_unique(explode(",", $value));
                    foreach ($users as $user) {
                        if (mb_substr($user, 0, 1) !== "#") { //Entity
                            if (!Server::getInstance()->getOfflinePlayer($user)->hasPlayedBefore()) {
                                $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("parser.no-player", [$user]));

                                return null;
                            }
                        }
                    }
                    break;
                case CommandParameter::TIME()->name():
                    $time = Utils::parseTime($value);
                    if ($time === null) {
                        $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("parser.invalid-time"));
                        return null;
                    }
                    break;
                case CommandParameter::WORLD()->name():
                    if (Server::getInstance()->getWorldManager()->getWorldByName($value) === null) {
                        $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("parser.invalid-world", [$value, implode(", ", WorldUtils::getWorldNames())]));
                        return null;
                    }
                    $world = $value;
                    break;
                case CommandParameter::RADIUS()->name():
                    if (mb_strtolower($value) === "#global") {
                        $radius = CommandData::GLOBAL_RADIUS;
                        break;
                    }

                    if (!ctype_digit($value)) {
                        $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("parser.invalid-radius"));
                        return null;
                    }

                    $radius = (int)$value;
                    $maxRadius = $this->getOwningPlugin()->getParsedConfig()->getMaxRadius();
                    if ($radius < 0 || ($maxRadius > 0 && $radius > $maxRadius)) {
                        $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("parser.invalid-radius"));
                        return null;
                    }
                    break;
                case CommandParameter::ACTIONS()->name():
                    $actions = Action::fromCommandArgument(mb_strtolower($value));
                    if ($actions === null) {
                        $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("parser.invalid-action", [$value, implode(", ", Action::COMMAND_ARGUMENTS)]));
                        return null;
                    }

                    break;
                case CommandParameter::INCLUDE()->name():
                    if (($inclusions = $this->parseItemArgument($sender, $value)) === null) {
                        return null;
                    }
                    break;
                case CommandParameter::EXCLUDE()->name():
                    if (($exclusions = $this->parseItemArgument($sender, $value)) === null) {
                        return null;
                    }
                    break;
            }
        }

        $cmdData = new CommandData($users, $time, $world, $radius, $actions, $inclusions, $exclusions, $additionalParams);

        if ($radius !== null && !$cmdData->isGlobalRadius()) {
            if ($sender instanceof Player) {
                //Don't allow a player to use normal radius in a different world
                if ($sender->getWorld()->getFolderName() !== $world) {
                    $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("parser.invalid-world-usage-player"));
                    return null;
                }
            } else {
                //Don't allow console to use integer radius, only global is allowed
                $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("parser.invalid-radius-usage-console"));
                return null;
            }
        }

        return $cmdData;
    }

    /**
     * @param CommandSender $sender
     * @param string $str
     * @return array<string, array<int, int>>|null
     */
    private function parseItemArgument(CommandSender $sender, string $str): ?array
    {
        /** @var int[] $itemIds */
        $itemIds = [];
        /** @var int[] $itemMetas */
        $itemMetas = [];
        /** @var StringToItemParser $itemParser */
        $itemParser = StringToItemParser::getInstance();

        foreach (explode(",", $str) as $strItem) {
            $item = $itemParser->parse($strItem);
            if ($item == null) {
                $sender->sendMessage(Main::MESSAGE_PREFIX . TextFormat::RED . $this->getLang()->translateString("parser.invalid-item-block", [$strItem]));
                return null;
            }

            $itemIds[] = $item->getId();
            $itemMetas[] = $item->getMeta();
        }

        return [
            "ids" => $itemIds,
            "metas" => $itemMetas
        ];
    }
}
