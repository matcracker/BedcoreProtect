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

namespace matcracker\BedcoreProtect\enums;

use matcracker\BedcoreProtect\Main;
use function mb_strtolower;

enum Action: int
{
    use NamedEnumTrait;
    use ValueBackedEnumTrait;

    case PLACE = 0;
    case BREAK = 1;
    case CLICK = 2;
    case SPAWN = 3;
    case DESPAWN = 4;
    case KILL = 5;
    case ADD = 6;
    case REMOVE = 7;
    case SESSION_JOIN = 8;
    case SESSION_LEFT = 9;
    case CHAT = 10;
    case COMMAND = 11;
    case UPDATE = 255;

    public function getMessage(): string
    {
        $action = match ($this) {
            self::SPAWN => self::PLACE,
            self::DESPAWN => self::BREAK,
            default => $this
        };

        return Main::getInstance()->getLanguage()->translateString("action." . mb_strtolower($action->name));
    }
}
