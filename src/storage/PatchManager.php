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

namespace matcracker\BedcoreProtect\storage;

use Generator;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\queries\DefaultQueriesTrait;
use matcracker\BedcoreProtect\storage\queries\QueriesConst;
use pocketmine\plugin\PluginException;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Await;
use function array_filter;
use function copy;
use function count;
use function fclose;
use function is_array;
use function stream_get_contents;
use function version_compare;
use function yaml_parse;

final class PatchManager
{
    use DefaultQueriesTrait {
        __construct as DefQueriesConstr;
    }

    public function __construct(private Main $plugin, DataConnector $connector)
    {
        $this->DefQueriesConstr($connector);
    }

    /**
     * Returns the last applied patch version or null if the patch is not applied.
     */
    public function patch(): ?string
    {
        $patchVersion = null;
        Await::f2c(
            function () use (&$patchVersion): Generator {
                /** @var array $rows */
                $rows = yield $this->executeSelect(QueriesConst::GET_DATABASE_STATUS);

                $versions = $this->getVersionsToPatch($rows[0]["version"]);
                if (count($versions) > 0) { //This means the database is not updated.
                    $parsedConfig = $this->plugin->getParsedConfig();
                    $dbType = $parsedConfig->getDatabaseType();

                    if ($parsedConfig->isSQLite()) { //Backup
                        $dbFilePath = $this->plugin->getDataFolder() . $parsedConfig->getDatabaseFileName();
                        if (!copy($dbFilePath, $dbFilePath . "." . $rows[0]["version"] . ".bak")) {
                            $this->plugin->getLogger()->warning($this->plugin->getLanguage()->translateString("database.version.backup-failed"));
                        }
                    }

                    /**
                     * @var string $version
                     */
                    foreach ($versions as $version => $dbTypes) {
                        $patchNumbers = $dbTypes[$dbType] ?? 0;
                        if ($patchNumbers <= 0) {
                            $this->plugin->getLogger()->debug("Skipped patch update v$version of $dbType.");
                            continue;
                        }

                        $this->plugin->getLogger()->info($this->plugin->getLanguage()->translateString("database.version.upgrading", [$version]));

                        yield $this->executeGeneric(QueriesConst::SET_FOREIGN_KEYS, ["flag" => false]);
                        for ($i = 1; $i <= $patchNumbers; $i++) {
                            yield $this->executeGeneric(QueriesConst::VERSION_PATCH($version, $i));
                        }
                        yield $this->executeGeneric(QueriesConst::SET_FOREIGN_KEYS, ["flag" => true]);

                        yield $this->executeChange(QueriesConst::ADD_DATABASE_VERSION, ["version" => $version]);
                        $patchVersion = $version;
                    }
                }
            }
        );
        //Pause the main thread until all the patches are applied.
        $this->connector->waitAll();
        return $patchVersion;
    }

    /**
     * @param string $db_version
     * @return int[][]
     */
    private function getVersionsToPatch(string $db_version): array
    {
        $res = $this->plugin->getResource("patches/.patches");
        if ($res === null) {
            throw new PluginException("Could not find \".patches\" file. Be sure to use the original .phar plugin file.");
        }

        $patchContent = stream_get_contents($res);
        fclose($res);
        if ($patchContent === false) {
            throw new PluginException("Could not read \".patches\" file.");
        }

        $patchConfig = yaml_parse($patchContent);
        if (!is_array($patchConfig)) {
            throw new PluginException("Could not parse \".patches\" file.");
        }

        return array_filter($patchConfig, function (string $patchVersion) use ($db_version): bool {
            return version_compare($this->plugin->getVersion(), $db_version) > 0
                && version_compare($patchVersion, $db_version) > 0;
        }, ARRAY_FILTER_USE_KEY);
    }
}
