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

namespace matcracker\BedcoreProtect\enums;

use InvalidArgumentException;
use pocketmine\utils\EnumTrait;

trait CustomEnumTrait
{
    use EnumTrait;

    /**
     * Returns the enum member matching the given name.
     * This is overridden to change the return typehint.
     *
     * @throws InvalidArgumentException if no member matches.
     */
    public static function fromString(string $name): self
    {
        //phpstan doesn't support generic traits yet :(
        /** @var self $result */
        $result = self::_registryFromString($name);
        return $result;
    }

    public function __toString(): string
    {
        return $this->enumName;
    }
}