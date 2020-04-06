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
use Generator;
use matcracker\BedcoreProtect\Inspector;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\math\Area;
use matcracker\BedcoreProtect\math\MathUtils;
use matcracker\BedcoreProtect\storage\QueryManager;
use matcracker\BedcoreProtect\ui\Forms;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\math\AxisAlignedBB;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;
use function array_key_exists;
use function count;
use function ctype_digit;
use function explode;
use function implode;
use function strtolower;

final class BCPCommand extends Command implements PluginIdentifiableCommand
{
    /** @var Main */
    private $plugin;
    /** @var QueryManager */
    private $queryManager;

    public function __construct(Main $plugin)
    {
        parent::__construct(
            'bedcoreprotect',
            $plugin->getLanguage()->translateString('command.description'),
            $plugin->getLanguage()->translateString('command.usage'),
            ['core', 'co', 'bcp']
        );
        $this->plugin = $plugin;
        $this->queryManager = $plugin->getDatabase()->getQueryManager();
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (count($args) === 0) {
            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . "&c{$this->getUsage()}"));

            return false;
        }

        $subCmd = $this->removeAbbreviation(strtolower($args[0]));
        if (!$sender->hasPermission('bcp.command.bedcoreprotect') || !$sender->hasPermission("bcp.subcommand.{$subCmd}")) {
            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.no-permission')));

            return false;
        }

        //Shared commands between player and console.
        switch ($subCmd) {
            case "help":
                array_key_exists(1, $args) ? BCPHelpCommand::showSpecificHelp($sender, $args[1]) : BCPHelpCommand::showGenericHelp($sender);

                return true;
            case 'reload':
                if ($this->plugin->reloadPlugin()) {
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.reload.success')));
                } else {
                    $this->plugin->restoreParsedConfig();
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.reload.no-success')));
                }

                return true;
            case 'status':
                Await::f2c(
                    function () use ($sender) : Generator {
                        $description = $this->plugin->getDescription();
                        $lang = $this->plugin->getLanguage();
                        $dbVersion = (string)(yield $this->plugin->getDatabase()->getStatus())[0]["version"];
                        $sender->sendMessage(TextFormat::colorize('&f----- &3' . Main::PLUGIN_NAME . ' &f-----'));
                        $sender->sendMessage(TextFormat::colorize('&3' . $lang->translateString('command.status.version', [$this->plugin->getVersion()])));
                        $sender->sendMessage(TextFormat::colorize('&3' . $lang->translateString('command.status.database-connection', [$this->plugin->getParsedConfig()->getPrintableDatabaseType()])));
                        $sender->sendMessage(TextFormat::colorize('&3' . $lang->translateString('command.status.database-version', [$dbVersion])));
                        $sender->sendMessage(TextFormat::colorize('&3' . $lang->translateString('command.status.blocksniper-hook', [$this->plugin->isBlockSniperHooked() ? $lang->translateString("generic.yes") : $lang->translateString("generic.no")])));
                        $sender->sendMessage(TextFormat::colorize('&3' . $lang->translateString('command.status.author', [implode(', ', $description->getAuthors())])));
                        $sender->sendMessage(TextFormat::colorize('&3' . $lang->translateString('command.status.website', [$description->getWebsite()])));
                    },
                    static function (): void {
                        //NOOP
                    }
                );
                return true;
            case 'lookup':
                if (array_key_exists(1, $args)) {
                    $parser = new CommandParser($sender->getName(), $this->plugin->getParsedConfig(), $args, ['time'], true);
                    if ($parser->parse()) {
                        $this->queryManager->getPluginQueries()->requestLookup($sender, $parser);
                    } else {
                        if (count($logs = Inspector::getCachedLogs($sender)) > 0) {
                            $page = 0;
                            $lines = 4;
                            if (array_key_exists(1, $args)) {
                                $split = explode(":", $args[1]);
                                if ($ctype = ctype_digit($split[0])) {
                                    $page = (int)$split[0];
                                }

                                if (array_key_exists(1, $split) && $ctype = ctype_digit($split[1])) {
                                    $lines = (int)$split[1];
                                }

                                if (!$ctype) {
                                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.error.no-numeric-value')));

                                    return true;
                                }
                            }
                            Inspector::parseLogs($sender, $logs, ($page - 1), $lines);
                        } else {
                            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                        }
                    }
                } else {
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.error.one-parameter')));
                }

                return true;
            case 'purge':
                if (array_key_exists(1, $args)) {
                    $parser = new CommandParser($sender->getName(), $this->plugin->getParsedConfig(), $args, ['time'], true);
                    if ($parser->parse()) {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.purge.started')));
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.purge.no-restart')));
                        $this->queryManager->getPluginQueries()->purge($parser->getTime(), function (int $affectedRows) use ($sender): void {
                            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.purge.success')));
                            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.purge.deleted-rows', [$affectedRows])));
                        });

                        return true;
                    } else {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                    }
                } else {
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.error.one-parameter')));
                }

                return true;
        }

        if (!($sender instanceof Player)) {
            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.error.no-console')));

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
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.inspect.disabled')));
                } else {
                    Inspector::addInspector($sender);
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.inspect.enabled')));
                }
                return true;
            case 'near':
                $near = 5;

                if (array_key_exists(1, $args)) {
                    if (!ctype_digit($args[1])) {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.error.no-numeric-value')));

                        return true;
                    }
                    $near = (int)$args[1];
                    $maxRadius = $this->plugin->getParsedConfig()->getMaxRadius();
                    if ($near < 1 || $near > $maxRadius) {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.near.range-value')));

                        return true;
                    }
                }

                $this->queryManager->getPluginQueries()->requestNearLog($sender, $sender, $near);

                return true;
            case 'rollback':
                if (array_key_exists(1, $args)) {
                    $parser = new CommandParser($sender->getName(), $this->plugin->getParsedConfig(), $args, ['time', 'radius'], true);
                    if ($parser->parse()) {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.rollback.started', [$sender->getLevel()->getName()])));

                        $bb = $this->getSelectionArea($sender) ?? MathUtils::getRangedVector($sender->asVector3(), $parser->getRadius());
                        $this->queryManager->rollback(new Area($sender->getLevel(), $bb), $parser);
                    } else {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                    }
                } else {
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.error.one-parameter')));
                }

                return true;
            case 'restore':
                if (array_key_exists(1, $args)) {
                    $parser = new CommandParser($sender->getName(), $this->plugin->getParsedConfig(), $args, ['time', 'radius'], true);
                    if ($parser->parse()) {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $this->plugin->getLanguage()->translateString('command.restore.started', [$sender->getLevel()->getName()])));

                        $bb = $this->getSelectionArea($sender) ?? MathUtils::getRangedVector($sender->asVector3(), $parser->getRadius());
                        $this->queryManager->restore(new Area($sender->getLevel(), $bb), $parser);
                    } else {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                    }
                } else {
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $this->plugin->getLanguage()->translateString('command.error.one-parameter')));
                }

                return true;
            default:
                $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . "&c{$this->getUsage()}"));
                return false;
        }
    }

    private function removeAbbreviation(string $subCmd): string
    {
        if ($subCmd === 'l') {
            $subCmd = 'lookup';
        } elseif ($subCmd === 'i') {
            $subCmd = 'inspect';
        } elseif ($subCmd === 'rb') {
            $subCmd = 'rollback';
        } elseif ($subCmd === 'rs') {
            $subCmd = 'restore';
        } elseif ($subCmd === 'ui') {
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

    public function getPlugin(): Plugin
    {
        return $this->plugin;
    }
}
