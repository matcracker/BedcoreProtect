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
use matcracker\BedcoreProtect\enums\AdditionalParameter;

class CommandData
{
    public const GLOBAL_RADIUS = -1;

    /**
     * @param string[]|null $users
     * @param int|null $time
     * @param string|null $world
     * @param int|null $radius
     * @param Action[]|null $actions
     * @param array<string, array<int, int>>|null $inclusions
     * @param array<string, array<int, int>>|null $exclusions
     * @param AdditionalParameter[] $additionalParams
     */
    public function __construct(
        private ?array  $users = null,
        private ?int    $time = null,
        private ?string $world = null,
        private ?int    $radius = null,
        private ?array  $actions = null,
        private ?array  $inclusions = null,
        private ?array  $exclusions = null,
        private array   $additionalParams = [])
    {
    }

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

    public function getActions(): ?array
    {
        return $this->actions;
    }

    public function getInclusions(): ?array
    {
        return $this->inclusions;
    }

    public function getExclusions(): ?array
    {
        return $this->exclusions;
    }

    public function getAdditionalParams(): array
    {
        return $this->additionalParams;
    }
}
