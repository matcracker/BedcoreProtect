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

namespace matcracker\BedcoreProtect\commands;

use matcracker\BedcoreProtect\enums\Action;

class CommandData
{
    public const GLOBAL_RADIUS = -1;

    /** @var string[]|null */
    protected ?array $users;
    protected ?int $time;
    protected ?int $radius;
    protected ?string $world;
    /** @var Action[]|null */
    protected ?array $actions;
    /** @var array[]|null */
    protected ?array $inclusions;
    /** @var array[]|null */
    protected ?array $exclusions;

    public function __construct(?array $users, ?int $time, ?string $world, ?int $radius, ?array $actions, ?array $inclusions, ?array $exclusions)
    {
        $this->users = $users;
        $this->time = $time;
        $this->world = $world;
        $this->radius = $radius;
        $this->actions = $actions;
        $this->inclusions = $inclusions;
        $this->exclusions = $exclusions;
    }

    /**
     * @return string[]|null
     */
    public function getUsers(): ?array
    {
        return $this->users;
    }

    public function getTime(): ?int
    {
        return $this->time;
    }

    public function getWorld(): ?string
    {
        return $this->world;
    }

    public function getRadius(): ?int
    {
        return $this->radius;
    }

    public function isGlobalRadius(): bool
    {
        return $this->radius === self::GLOBAL_RADIUS;
    }

    /**
     * @return Action[]|null
     */
    public function getActions(): ?array
    {
        return $this->actions;
    }

    /**
     * @return array<string, array<int, int>>|null
     */
    public function getInclusions(): ?array
    {
        return $this->inclusions;
    }

    /**
     * @return array<string, array<int, int>>|null
     */
    public function getExclusions(): ?array
    {
        return $this->exclusions;
    }
}
