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

use Generator;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\queries\QueriesConst;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Await;
use UnexpectedValueException;
use function array_filter;
use function count;
use function fclose;
use function is_string;
use function stream_get_contents;
use function version_compare;
use function yaml_parse;

final class PatchManager
{
    /** @var DataConnector */
    private $connector;
    /** @var Main */
    private $plugin;

    public function __construct(Main $plugin, DataConnector $connector)
    {
        $this->plugin = $plugin;
        $this->connector = $connector;
    }

    /**
     * Return true if patch updated the database.
     * @return bool
     */
    public function patch(): bool
    {
        $updated = false;
        Await::f2c(
            function () use (&$updated) : Generator {
                /** @var array $rows */
                $rows = yield $this->executeSelect(QueriesConst::GET_DATABASE_STATUS);

                $versions = $this->getVersionsToPatch($rows[0]['version']);
                if ($updated = (count($versions) > 0)) { //This means the database is not updated.
                    $pluginVersion = $this->plugin->getVersion();
                    $dbType = $this->plugin->getParsedConfig()->getDatabaseType();

                    $this->plugin->getLogger()->info($this->plugin->getLanguage()->translateString("database.version.upgrading", [$pluginVersion]));

                    /**
                     * @var string $version
                     * @var int[] $dbTypes
                     */
                    foreach ($versions as $version => $dbTypes) {
                        $patchNumbers = $dbTypes[$dbType];
                        for ($i = 1; $i <= $patchNumbers; $i++) {
                            yield $this->executeGeneric(QueriesConst::VERSION_PATCH($version, $i));
                        }
                    }

                    yield $this->executeChange(QueriesConst::UPDATE_DATABASE_VERSION, ['version' => $pluginVersion]);
                }
            },
            static function (): void {
                //NOOP
            }
        );
        //Pause the main thread until all the patches are applied.
        $this->connector->waitAll();
        return $updated;
    }

    private function executeSelect(string $query, array $args = []): Generator
    {
        $this->connector->executeSelect($query, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    /**
     * @param string $db_version
     * @return string[]
     */
    private function getVersionsToPatch(string $db_version): array
    {
        $patchContent = stream_get_contents(($res = $this->plugin->getResource('patches/.patches')));

        if (!is_string($patchContent)) {
            throw new UnexpectedValueException("Could not get patch data.");
        }

        $patchConfig = yaml_parse($patchContent) ?? [];
        fclose($res);
        return array_filter($patchConfig, static function (string $version) use ($db_version): bool {
            return version_compare($version, $db_version) > 0;
        }, ARRAY_FILTER_USE_KEY);
    }

    private function executeGeneric(string $query, array $args = []): Generator
    {
        $this->connector->executeGeneric($query, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    private function executeChange(string $query, array $args = []): Generator
    {
        $this->connector->executeChange($query, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }
}
