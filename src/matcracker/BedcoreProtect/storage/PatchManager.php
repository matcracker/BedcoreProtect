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

namespace matcracker\BedcoreProtect\storage;

use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\queries\QueriesConst;
use poggit\libasynql\DataConnector;

final class PatchManager
{
    /**@var DataConnector $connector */
    private $connector;
    /**@var Main $plugin */
    private $plugin;

    public function __construct(Main $plugin, DataConnector $connector)
    {
        $this->plugin = $plugin;
        $this->connector = $connector;
    }

    private function getVersionsToPatch(string $db_version): array
    {
        $patchConfig = yaml_parse(stream_get_contents(($res = $this->plugin->getResource('patches/.patches')))) ?? [];
        fclose($res);
        return array_filter($patchConfig, static function (string $version) use ($db_version): bool {
            return version_compare($version, $db_version) > 0;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Return true if patch updated the database.
     * @return bool
     */
    public function patch(): bool
    {
        $updated = false;
        $this->connector->executeSelect(QueriesConst::GET_DATABASE_STATUS, [], function (array $rows) use (&$updated): void {
            $versions = $this->getVersionsToPatch($rows[0]['version']);
            if ($updated = (!empty($versions))) { //This means the database is not updated.
                /**
                 * @var string $version
                 * @var int $patchNumbers
                 */
                foreach ($versions as $version => $patchNumbers) {
                    for ($i = 1; $i <= $patchNumbers; $i++) {
                        $this->connector->executeGeneric(QueriesConst::VERSION_PATCH($version, $i));
                    }
                }
            }
        });
        $this->connector->waitAll();
        if ($updated) {
            $this->connector->executeChange(QueriesConst::UPDATE_DATABASE_VERSION, [
                'version' => $this->plugin->getVersion()
            ]);
        }
        return $updated;
    }
}