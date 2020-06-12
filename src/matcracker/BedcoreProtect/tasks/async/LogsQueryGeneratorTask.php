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

namespace matcracker\BedcoreProtect\tasks\async;

use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\serializable\SerializablePosition;
use function mb_substr;

final class LogsQueryGeneratorTask extends QueryGeneratorTask
{
    /** @var string */
    private $uuid;
    /** @var SerializablePosition[] */
    private $positions;
    /** @var Action */
    private $action;
    /** @var float */
    private $time;
    /** @var bool */
    private $isSQLite;

    /**
     * LogsQueryGen constructor.
     * @param string $uuid
     * @param SerializablePosition[] $positions
     * @param Action $action
     * @param float $time
     * @param bool $isSQLite
     * @param callable|null $onComplete
     */
    public function __construct(string $uuid, array $positions, Action $action, float $time, bool $isSQLite, ?callable $onComplete)
    {
        parent::__construct(-1, $onComplete);
        $this->uuid = $uuid;
        $this->positions = $positions;
        $this->action = $action;
        $this->time = $time;
        $this->isSQLite = $isSQLite;
    }

    public function onRun(): void
    {
        $query = /**@lang text */
            'INSERT INTO log_history(who, x, y, z, world_name, action, time) VALUES';

        if ($this->isSQLite) {
            $qTime = "STRFTIME('%Y-%m-%d %H:%M:%f', {$this->time}, 'unixepoch', 'localtime')";
        } else {
            $qTime = "FROM_UNIXTIME({$this->time})";
        }

        foreach ($this->positions as $position) {
            $x = $position->getX();
            $y = $position->getY();
            $z = $position->getZ();
            $query .= "((SELECT uuid FROM entities WHERE uuid = '{$this->uuid}'), '{$x}', '{$y}', '{$z}', '{$position->getWorldName()}', '{$this->action->getType()}', {$qTime}),";
        }

        $query = mb_substr($query, 0, -1) . ';';
        $this->setResult($query);
    }
}
