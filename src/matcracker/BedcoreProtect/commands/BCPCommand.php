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

namespace matcracker\BedcoreProtect\commands;

use BlockHorizons\BlockSniper\sessions\SessionManager;
use matcracker\BedcoreProtect\Inspector;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\utils\Area;
use matcracker\BedcoreProtect\utils\MathUtils;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\math\AxisAlignedBB;
use pocketmine\Player;

final class BCPCommand extends Command
{

    private $plugin;
    private $queries;

    public function __construct(Main $plugin)
    {
        parent::__construct(
            "bedcoreprotect",
            "It runs the BedcoreProtect commands.",
            "Usage: /bcp help to display commands list",
            ["core", "co", "bcp"]
        );
        $this->plugin = $plugin;
        $this->queries = $plugin->getDatabase()->getQueries();
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (empty($args)) {
            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&c{$this->getUsage()}"));

            return false;
        }

        $subCmd = $this->removeAbbreviation(strtolower($args[0]));
        if (!$sender->hasPermission("bcp.command.bedcoreprotect") || !$sender->hasPermission("bcp.subcommand.{$subCmd}")) {
            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&cYou don't have permission to run this command."));

            return false;
        }

        //Shared commands between player and console.
        switch ($subCmd) {
            case "help":
                isset($args[1]) ? BCPHelpCommand::showSpecificHelp($sender, $args[1]) : BCPHelpCommand::showGenericHelp($sender);

                return true;
            case "reload":
                if ($this->plugin->reloadPlugin()) {
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "Plugin configuration reloaded."));
                } else {
                    $this->plugin->restoreParsedConfig();
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&cPlugin configuration not reloaded due to some errors. Check your console to see the errors."));
                    $sender->sendMessage(Utils::translateColors("&cUntil you fix them, it will be used the old configuration."));
                }

                return true;
            case "status":
                $description = $this->plugin->getDescription();
                $sender->sendMessage(Utils::translateColors("&f----- &3" . Main::PLUGIN_NAME . " &f-----"));
                $sender->sendMessage(Utils::translateColors("&3Version:&f " . $description->getVersion()));
                $sender->sendMessage(Utils::translateColors("&3Database connection:&f " . $this->plugin->getParsedConfig()->getPrintableDatabaseType()));
                $sender->sendMessage(Utils::translateColors("&3BlockSniper hook:&f " . ($this->plugin->isBlockSniperHooked() ? "Yes" : "No")));
                $sender->sendMessage(Utils::translateColors("&3Author:&f " . implode(",", $description->getAuthors())));
                $sender->sendMessage(Utils::translateColors("&3Website:&f " . $description->getWebsite()));

                return true;
            case "lookup":
                if (isset($args[1])) {
                    $parser = new CommandParser($sender->getName(), $this->plugin->getParsedConfig(), $args, ["time"], true);
                    if ($parser->parse()) {
                        $this->queries->requestLookup($sender, $parser);
                    } else {
                        if (count($logs = Inspector::getCachedLogs($sender)) > 0) {
                            $page = 0;
                            $lines = 4;
                            if (isset($args[1])) {
                                $split = explode(":", $args[1]);
                                if ($ctype = ctype_digit($split[0])) {
                                    $page = (int)$split[0];
                                }

                                if (isset($split[1]) && $ctype = ctype_digit($split[1])) {
                                    $lines = (int)$split[1];
                                }

                                if (!$ctype) {
                                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&cWrong page/line value inserted!"));

                                    return true;
                                }
                            }
                            Inspector::parseLogs($sender, $logs, ($page - 1), $lines);
                        } else {
                            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                        }
                    }
                } else {
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&cYou must add at least one parameter."));
                }

                return true;
            case "purge":
                if (isset($args[1])) {
                    $parser = new CommandParser($sender->getName(), $this->plugin->getParsedConfig(), $args, ["time"], true);
                    if ($parser->parse()) {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "Data purge started. This may take some time."));
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "Do not restart your server until completed."));
                        $this->queries->purge($parser->getTime(), static function (int $affectedRows) use ($sender) {
                            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "Data purge successful."));
                            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "{$affectedRows} rows of data deleted."));
                        });

                        return true;
                    } else {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                    }
                } else {
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&cYou must add at least one parameter."));
                }

                return true;
        }

        if (!($sender instanceof Player)) {
            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&cYou can't run this command from console."));

            return false;
        }

        //Only players commands.
        switch ($subCmd) {
            case "inspect":
                $b = Inspector::isInspector($sender);
                $b ? Inspector::removeInspector($sender) : Inspector::addInspector($sender);
                $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . ($b ? "Disabled" : "Enabled") . " inspector mode."));

                return true;
            case "near":
                $near = 5;

                if (isset($args[1])) {
                    if (!ctype_digit($args[1])) {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&cThe near value must be numeric!"));

                        return true;
                    }
                    $near = (int)$args[1];
                    $maxRadius = $this->plugin->getParsedConfig()->getMaxRadius();
                    if ($near < 1 || $near > $maxRadius) {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&cThe near value must be between 1 and {$maxRadius}!"));

                        return true;
                    }
                }

                $this->queries->requestNearLog($sender, $sender, $near);

                return true;
            case "rollback":
                if (isset($args[1])) {
                    $parser = new CommandParser($sender->getName(), $this->plugin->getParsedConfig(), $args, ["time", "radius"], true);
                    if ($parser->parse()) {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "Starting rollback on \"{$sender->getLevel()->getFolderName()}\"."));

                        $bb = $this->getSelectionArea($sender) ?? MathUtils::getRangedVector($sender->asVector3(), $parser->getRadius());
                        $this->queries->rollback(new Area($sender->getLevel(), $bb), $parser);
                    } else {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                    }
                } else {
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&cYou must add at least one parameter."));
                }

                return true;
            case "restore":
                if (isset($args[1])) {
                    $parser = new CommandParser($sender->getName(), $this->plugin->getParsedConfig(), $args, ["time", "radius"], true);
                    if ($parser->parse()) {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "Restore started on \"{$sender->getLevel()->getFolderName()}\"."));

                        $bb = $this->getSelectionArea($sender) ?? MathUtils::getRangedVector($sender->asVector3(), $parser->getRadius());
                        $this->queries->restore(new Area($sender->getLevel(), $bb), $parser);
                    } else {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                    }
                } else {
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&cYou must add at least one parameter."));
                }

                return true;
        }

        return false;
    }

    private function removeAbbreviation(string $subCmd): string
    {
        if ($subCmd === "l") {
            $subCmd = "lookup";
        } else if ($subCmd === "i") {
            $subCmd = "inspect";
        } else if ($subCmd === "rb") {
            $subCmd = "rollback";
        } else if ($subCmd === "rs") {
            $subCmd = "restore";
        }

        return $subCmd;
    }

    private function getSelectionArea(Player $player): ?AxisAlignedBB
    {
        if ($this->plugin->isBlockSniperHooked()) {
            $session = SessionManager::getPlayerSession($player);
            if ($session !== null) {
                $selection = $session->getSelection();
                if ($selection->ready()) {
                    return $selection->box();
                }
            }
        }

        return null;
    }
}