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

use pocketmine\utils\EnumTrait;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever enum members are added, removed or changed.
 * @see EnumTrait::_generateMethodAnnotations()
 *
 * @method static self OPTIMIZE()
 */
final class AdditionalParameter
{
    public const KEY_CHAR = "#";

    use CustomEnumTrait {
        register as Enum_register;
        __construct as Enum___construct;
    }

    public function __construct(string $enumName)
    {
        $this->Enum___construct($enumName);
    }

    protected static function setup(): void
    {
        self::registerAll(
            new self("optimize")
        );
    }

    protected static function register(self $member): void
    {
        self::Enum_register($member);
    }
}
