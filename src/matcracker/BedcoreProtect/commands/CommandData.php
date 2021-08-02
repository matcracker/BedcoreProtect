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

    /** @var string[]|null */
    protected ?array $users;
    protected ?int $time;
    protected ?int $radius;
    protected ?string $world;
    /** @var Action[]|null */
    protected ?array $actions;
    /** @var array<string, array<int, int>>|null */
    protected ?array $inclusions;
    /** @var array<string, array<int, int>>|null */
    protected ?array $exclusions;
    /** @var AdditionalParameter[] */
    protected array $additionalParams;

    public function __construct(?array $users = null, ?int $time = null, ?string $world = null, ?int $radius = null, ?array $actions = null, ?array $inclusions = null, ?array $exclusions = null, array $additionalParams = [])
    {
        $this->users = $users;
        $this->time = $time;
        $this->world = $world;
        $this->radius = $radius;
        $this->actions = $actions;
        $this->inclusions = $inclusions;
        $this->exclusions = $exclusions;
        $this->additionalParams = $additionalParams;
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

    /**
     * @return AdditionalParameter[]
     */
    public function getAdditionalParams(): array
    {
        return $this->additionalParams;
    }
}
