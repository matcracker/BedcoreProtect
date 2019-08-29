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
use matcracker\BedcoreProtect\math\Area;
use matcracker\BedcoreProtect\math\MathUtils;
use matcracker\BedcoreProtect\ui\Forms;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\math\AxisAlignedBB;
use pocketmine\Player;
use SOFe\AwaitGenerator\Await;

final class BCPCommand extends Command
{

    private $plugin;
    private $queries;

    public function __construct(Main $plugin)
    {
        parent::__construct(
            'bedcoreprotect',
            $plugin->getLanguage()->translateString('command.description'),
            $plugin->getLanguage()->translateString('command.usage'),
            ['core', 'co', 'bcp']
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
        if (!$sender->hasPermission('bcp.command.bedcoreprotect') || !$sender->hasPermission("bcp.subcommand.{$subCmd}")) {
            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.no-permission')));

            return false;
        }

        //Shared commands between player and console.
        switch ($subCmd) {
            case "help":
                isset($args[1]) ? BCPHelpCommand::showSpecificHelp($sender, $args[1]) : BCPHelpCommand::showGenericHelp($sender);

                return true;
            case 'reload':
                if ($this->plugin->reloadPlugin()) {
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.reload.success')));
                } else {
                    $this->plugin->restoreParsedConfig();
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.reload.no-success')));
                }

                return true;
            case 'status':
                Await::f2c(function () use ($sender) {
                    $description = $this->plugin->getDescription();
                    $lang = $this->plugin->getLanguage();
                    $dbVersion = (string)yield $this->plugin->getDatabase()->getStatus()[0]["version"];
                    $sender->sendMessage(Utils::translateColors('&f----- &3' . Main::PLUGIN_NAME . ' &f-----'));
                    $sender->sendMessage(Utils::translateColors('&3' . $lang->translateString('command.status.version', [$this->plugin->getVersion()])));
                    $sender->sendMessage(Utils::translateColors('&3' . $lang->translateString('command.status.database-connection', [$this->plugin->getParsedConfig()->getPrintableDatabaseType()])));
                    $sender->sendMessage(Utils::translateColors('&3' . $lang->translateString('command.status.database-version', [$dbVersion])));
                    $sender->sendMessage(Utils::translateColors('&3' . $lang->translateString('command.status.blocksniper-hook', [$this->plugin->isBlockSniperHooked() ? $lang->translateString("generic.yes") : $lang->translateString("generic.no")])));
                    $sender->sendMessage(Utils::translateColors('&3' . $lang->translateString('command.status.author', [implode(', ', $description->getAuthors())])));
                    $sender->sendMessage(Utils::translateColors('&3' . $lang->translateString('command.status.website', [$description->getWebsite()])));
                }, function () {
                    //NOOP
                });
                return true;
            case 'lookup':
                if (isset($args[1])) {
                    $parser = new CommandParser($sender->getName(), $this->plugin->getParsedConfig(), $args, ['time'], true);
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
                                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.error.no-numeric-value')));

                                    return true;
                                }
                            }
                            Inspector::parseLogs($sender, $logs, ($page - 1), $lines);
                        } else {
                            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                        }
                    }
                } else {
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.error.one-parameter')));
                }

                return true;
            case 'purge':
                if (isset($args[1])) {
                    $parser = new CommandParser($sender->getName(), $this->plugin->getParsedConfig(), $args, ['time'], true);
                    if ($parser->parse()) {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.purge.started')));
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.purge.no-restart')));
                        $this->queries->purge($parser->getTime(), function (int $affectedRows) use ($sender): void {
                            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.purge.success')));
                            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.purge.deleted-rows', [$affectedRows])));
                        });

                        return true;
                    } else {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                    }
                } else {
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.error.one-parameter')));
                }

                return true;
        }

        if (!($sender instanceof Player)) {
            $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.error.no-console')));

            return false;
        }

        //Only players commands.
        switch ($subCmd) {
            case 'menu':
            case 'ui':
                $sender->sendForm((new Forms($this->plugin->getParsedConfig()))->getMainMenu());
                return true;
            case 'inspect':
                if (Inspector::isInspector($sender)) {
                    Inspector::removeInspector($sender);
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.inspect.disabled')));
                } else {
                    Inspector::addInspector($sender);
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.inspect.enabled')));
                }
                return true;
            case 'near':
                $near = 5;

                if (isset($args[1])) {
                    if (!ctype_digit($args[1])) {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.error.no-numeric-value')));

                        return true;
                    }
                    $near = (int)$args[1];
                    $maxRadius = $this->plugin->getParsedConfig()->getMaxRadius();
                    if ($near < 1 || $near > $maxRadius) {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.near.range-value')));

                        return true;
                    }
                }

                $this->queries->requestNearLog($sender, $sender, $near);

                return true;
            case 'rollback':
                if (isset($args[1])) {
                    $parser = new CommandParser($sender->getName(), $this->plugin->getParsedConfig(), $args, ['time', 'radius'], true);
                    if ($parser->parse()) {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.rollback.started', [$sender->getLevel()->getName()])));

                        $bb = $this->getSelectionArea($sender) ?? MathUtils::getRangedVector($sender->asVector3(), $parser->getRadius());
                        $this->queries->rollback(new Area($sender->getLevel(), $bb), $parser);
                    } else {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                    }
                } else {
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.error.one-parameter')));
                }

                return true;
            case 'restore':
                if (isset($args[1])) {
                    $parser = new CommandParser($sender->getName(), $this->plugin->getParsedConfig(), $args, ['time', 'radius'], true);
                    if ($parser->parse()) {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.restore.started', [$sender->getLevel()->getName()])));

                        $bb = $this->getSelectionArea($sender) ?? MathUtils::getRangedVector($sender->asVector3(), $parser->getRadius());
                        $this->queries->restore(new Area($sender->getLevel(), $bb), $parser);
                    } else {
                        $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                    }
                } else {
                    $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.error.one-parameter')));
                }

                return true;
            default:
                $sender->sendMessage(Utils::translateColors(Main::MESSAGE_PREFIX . "&c{$this->getUsage()}"));
                return false;
        }
    }

    private function removeAbbreviation(string $subCmd): string
    {
        if ($subCmd === 'l') {
            $subCmd = 'lookup';
        } else if ($subCmd === 'i') {
            $subCmd = 'inspect';
        } else if ($subCmd === 'rb') {
            $subCmd = 'rollback';
        } else if ($subCmd === 'rs') {
            $subCmd = 'restore';
        } else if ($subCmd === 'ui') {
            $subCmd = 'menu';
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