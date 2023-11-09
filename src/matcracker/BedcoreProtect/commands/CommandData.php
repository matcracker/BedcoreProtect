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

namespace matcracker\BedcoreProtect\commands;

use matcracker\BedcoreProtect\enums\Action;
use matcracker\BedcoreProtect\enums\AdditionalParameter;

class CommandData
{
    public const GLOBAL_RADIUS = -1;

    /**
     * @param string[] $users
     * @param int|null $time
     * @param string $world
     * @param int|null $radius
     * @param Action[] $actions
     * @param string[] $inclusions
     * @param string[] $exclusions
     * @param AdditionalParameter[] $additionalParams
     */
    public function __construct(
        private readonly array  $users,
        private readonly ?int   $time,
        private readonly string $world,
        private readonly ?int   $radius,
        private readonly array  $actions,
        private readonly array  $inclusions,
        private readonly array  $exclusions,
        private readonly array  $additionalParams)
    {
    }

    /**
     * @return string[]
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    public function getTime(): ?int
    {
        return $this->time;
    }

    public function getWorld(): string
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
     * @return Action[]
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * @return string[]
     */
    public function getInclusions(): array
    {
        return $this->inclusions;
    }

    /**
     * @return string[]
     */
    public function getExclusions(): array
    {
        return $this->exclusions;
    }

    /**
     * @return AdditionalParameter[]
     */
    public function getAdditionalParams(): array
    {
        return $this->additionalParams;
    }
}
