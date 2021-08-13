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

namespace matcracker\BedcoreProtect\storage\queries;

use Generator;
use poggit\libasynql\DataConnector;
use SOFe\AwaitGenerator\Await;

trait DefaultQueriesTrait
{
    public function __construct(protected DataConnector $connector)
    {
    }

    final protected function executeGeneric(string $query, array $args = []): Generator
    {
        $this->connector->executeGeneric($query, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    final protected function executeChange(string $query, array $args = []): Generator
    {
        $this->connector->executeChange($query, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    final protected function executeInsert(string $query, array $args = []): Generator
    {
        $this->connector->executeInsert($query, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    final protected function executeSelect(string $query, array $args = []): Generator
    {
        $this->connector->executeSelect($query, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    /**
     * @param string $query
     * @param array $args
     * @param bool $multiParams if true, returns all parameters of callable 'onInserted(int $insertId, int $affectedRows)' instead of only $insertId.
     * @return Generator
     */
    final protected function executeInsertRaw(string $query, array $args = [], bool $multiParams = false): Generator
    {
        $this->connector->executeInsertRaw($query, $args, yield ($multiParams ? Await::RESOLVE_MULTI : Await::RESOLVE), yield Await::REJECT);
        return yield Await::ONCE;
    }
}
