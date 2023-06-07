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

namespace matcracker\BedcoreProtect\storage\patches;

use Generator;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\queries\QueriesConst;
use pocketmine\plugin\PluginException;
use poggit\libasynql\DataConnector;
use poggit\libasynql\SqlDialect;
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
    /** @var array<string, PluginPatch> $patches */
    private array $patches = [];

    public function __construct(private readonly Main $plugin, private readonly DataConnector $connector)
    {
        $this->registerPluginPatch(new PluginPatchV110($this->connector, $this->plugin->getLogger()));
    }

    private function registerPluginPatch(PluginPatch $patch): void
    {
        $this->patches[$patch->getVersion()] = $patch;
    }

    public function isRegisteredPluginPatch(string $version): bool
    {
        return isset($this->patches[$version]);
    }

    public function getPluginPatch(string $version): ?PluginPatch
    {
        if ($this->isRegisteredPluginPatch($version)) {
            return $this->patches[$version];
        }

        return null;
    }

    /**
     * Returns the last applied patch version or null if the patch is not applied.
     */
    public function patch(): Generator
    {
        /** @var string|null $patchVersion */
        $patchVersion = null;

        /** @var array $row */
        [$row] = yield from $this->connector->asyncSelect(QueriesConst::GET_DATABASE_STATUS);

        $versions = $this->getVersionsToPatch($row["version"]);
        if (count($versions) > 0) { //This means the database is not updated.
            $parsedConfig = $this->plugin->getParsedConfig();
            $dbType = $parsedConfig->getDatabaseType();

            if ($parsedConfig->isSQLite()) { //Backup
                $dbFilePath = $this->plugin->getDataFolder() . $parsedConfig->getDatabaseFileName();
                if (!copy($dbFilePath, $dbFilePath . "." . $row["version"] . ".bak")) {
                    $this->plugin->getLogger()->warning($this->plugin->getLanguage()->translateString("database.version.backup-failed"));
                }
            }

            /**
             * @var string $version
             */
            foreach ($versions as $version => $dbTypes) {
                $maxPatchId = $dbTypes[$dbType] ?? 0;
                if ($maxPatchId <= 0) {
                    $this->plugin->getLogger()->debug("Skipped patch update v$version of $dbType.");
                    continue;
                }

                $this->plugin->getLogger()->info($this->plugin->getLanguage()->translateString("database.version.upgrading", [$version]));

                yield from $this->connector->asyncGeneric(QueriesConst::SET_FOREIGN_KEYS, ["flag" => false]);

                for ($patchId = 1; $patchId <= $maxPatchId; $patchId++) {
                    yield from $this->connector->asyncGeneric(QueriesConst::VERSION_PATCH($version, $patchId));
                    if (($pluginPatch = $this->getPluginPatch($version)) !== null) {
                        match ($dbType) {
                            SqlDialect::SQLITE => yield from $pluginPatch->asyncPatchSQLite($patchId),
                            SqlDialect::MYSQL => yield from $pluginPatch->asyncPatchMySQL($patchId)
                        };
                    }
                }
                yield from $this->connector->asyncGeneric(QueriesConst::SET_FOREIGN_KEYS, ["flag" => true]);

                yield from $this->connector->asyncChange(QueriesConst::ADD_DATABASE_VERSION, ["version" => $version]);
                $patchVersion = $version;
            }
        }

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
