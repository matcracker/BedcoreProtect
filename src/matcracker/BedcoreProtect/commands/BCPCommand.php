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
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;
use function count;
use function ctype_digit;
use function explode;
use function implode;
use function mb_strtolower;
use function version_compare;
use const PHP_INT_MAX;

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
        $lang = $this->plugin->getLanguage();

        if (count($args) === 0) {
            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . "&c{$this->getUsage()}"));

            return false;
        }

        $subCmd = $this->removeAbbreviation(mb_strtolower($args[0]));
        if (!$sender->hasPermission("bcp.subcommand.{$subCmd}") || !$sender->hasPermission('bcp.command.bedcoreprotect')) {
            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $lang->translateString('command.no-permission')));

            return false;
        }

        $config = $this->plugin->getParsedConfig();

        //Shared commands between player and console.
        switch ($subCmd) {
            case "help":
                $helpCmd = new BCPHelpCommand($sender, $lang);
                if (isset($args[1])) {
                    $helpCmd->showCommandHelp($args[1]);
                } else {
                    $helpCmd->showGenericHelp();
                }

                return true;
            case 'reload':
                if ($this->plugin->reloadPlugin()) {
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('command.reload.success')));
                } else {
                    $this->plugin->restoreParsedConfig();
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $lang->translateString('command.reload.no-success')));
                }

                return true;
            case 'status':
                Await::f2c(
                    function () use ($sender, $config, $lang) : Generator {
                        $description = $this->plugin->getDescription();
                        $pluginVersion = $description->getVersion();
                        $dbVersion = (string)(yield $this->plugin->getDatabase()->getStatus())[0]["version"];

                        if (version_compare($pluginVersion, $dbVersion) > 0) {
                            //Database version could be minor respect the plugin, in this case I apply a BC suffix (Backward Compatibility)
                            $dbVersion .= ' (' . $lang->translateString("command.status.bc") . ')';
                        }
                        $sender->sendMessage(TextFormat::colorize('&f----- &3' . Main::PLUGIN_NAME . ' &f-----'));
                        $sender->sendMessage(TextFormat::colorize('&3' . $lang->translateString('command.status.version', [$pluginVersion])));
                        $sender->sendMessage(TextFormat::colorize('&3' . $lang->translateString('command.status.database-connection', [$config->getPrintableDatabaseType()])));
                        $sender->sendMessage(TextFormat::colorize('&3' . $lang->translateString('command.status.database-version', [$dbVersion])));
                        $sender->sendMessage(TextFormat::colorize('&3' . $lang->translateString('command.status.author', [implode(', ', $description->getAuthors())])));
                        $sender->sendMessage(TextFormat::colorize('&3' . $lang->translateString('command.status.website', [$description->getWebsite()])));
                    }
                );
                return true;
            case 'lookup':
                if (isset($args[1])) {
                    $parser = new CommandParser($sender->getName(), $config, $args, ['time'], true);
                    if ($parser->parse()) {
                        $this->queryManager->getPluginQueries()->requestLookup($sender, $parser);
                    } else {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                    }
                } else {
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $lang->translateString('command.error.one-parameter')));
                }
                return true;
            case 'show':
                if (isset($args[1])) {
                    if (count($logs = Inspector::getSavedLogs($sender)) > 0) {
                        $page = 0;
                        $lines = 4;
                        $split = explode(":", $args[1]);
                        if ($pageType = ctype_digit($split[0])) {
                            $page = (int)$split[0];
                        }

                        $lineType = true;
                        if (isset($split[1]) && $lineType = ctype_digit($split[1])) {
                            $lines = (int)$split[1];
                        }

                        if (!$pageType || !$lineType) {
                            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $lang->translateString('command.error.no-numeric-value')));

                            return true;
                        }

                        Inspector::parseLogs($sender, $logs, ($page - 1), $lines);
                    } else {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $lang->translateString('command.show.no-logs')));
                    }
                } else {
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $lang->translateString('command.error.one-parameter')));
                }
                return true;
            case 'purge':
                if (isset($args[1])) {
                    $parser = new CommandParser($sender->getName(), $config, $args, ['time'], true);
                    if ($parser->parse()) {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('command.purge.started')));
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('command.purge.no-restart')));
                        $this->queryManager->getPluginQueries()->purge($parser->getTime() ?? PHP_INT_MAX, function (int $affectedRows) use ($sender, $lang): void {
                            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('command.purge.success')));
                            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('command.purge.deleted-rows', [$affectedRows])));
                        });
                    } else {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                    }
                } else {
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $lang->translateString('command.error.one-parameter')));
                }

                return true;
        }

        if (!($sender instanceof Player)) {
            $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $lang->translateString('command.error.no-console')));

            return false;
        }

        //Only players commands.
        switch ($subCmd) {
            case 'menu':
            case 'ui':
                $sender->sendForm((new Forms($config))->getMainMenu());
                return true;
            case 'inspect':
                if (Inspector::isInspector($sender)) {
                    Inspector::removeInspector($sender);
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('command.inspect.disabled')));
                } else {
                    Inspector::addInspector($sender);
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('command.inspect.enabled')));
                }
                return true;
            case 'near':
                $near = 5;

                if (isset($args[1])) {
                    if (!ctype_digit($args[1])) {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $lang->translateString('command.error.no-numeric-value')));

                        return true;
                    }
                    $near = (int)$args[1];
                    $maxRadius = $config->getMaxRadius();
                    if ($near < 1 || $near > $maxRadius) {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $lang->translateString('command.near.range-value')));

                        return true;
                    }
                }

                $this->queryManager->getPluginQueries()->requestNearLog($sender, $sender, $near);

                return true;
            case 'rollback':
                if (isset($args[1])) {
                    $parser = new CommandParser($sender->getName(), $config, $args, ['time', 'radius'], true);
                    if ($parser->parse()) {
                        $level = $sender->getLevelNonNull();
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('command.rollback.started', [$level->getName()])));

                        $bb = MathUtils::getRangedVector($sender->asVector3(), $parser->getRadius() ?? 0);
                        $this->queryManager->rollback(new Area($level, $bb), $parser);
                    } else {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                    }
                } else {
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $lang->translateString('command.error.one-parameter')));
                }

                return true;
            case 'restore':
                if (isset($args[1])) {
                    $parser = new CommandParser($sender->getName(), $config, $args, ['time', 'radius'], true);
                    if ($parser->parse()) {
                        $level = $sender->getLevelNonNull();
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('command.restore.started', [$level->getName()])));

                        $bb = MathUtils::getRangedVector($sender->asVector3(), $parser->getRadius() ?? $config->getDefaultRadius());
                        $this->queryManager->restore(new Area($level, $bb), $parser);
                    } else {
                        $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . "&c{$parser->getErrorMessage()}"));
                    }
                } else {
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . '&c' . $lang->translateString('command.error.one-parameter')));
                }

                return true;
            case 'undo':
                if (!$this->queryManager->undoRollback($sender)) {
                    $sender->sendMessage(TextFormat::colorize(Main::MESSAGE_PREFIX . $lang->translateString('command.undo.not-found')));
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

    /**
     * @return Main
     */
    public function getPlugin(): Plugin
    {
        return $this->plugin;
    }
}
