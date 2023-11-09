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

enum ActionCommandArgument: string
{
    use NamedEnumTrait;
    use ValueBackedEnumTrait;

    case ALL = "all";
    case PLACE_BREAK = "block";
    case PLACE = "+block";
    case BREAK = "-block";
    case CLICK = "click";
    case ADD_REMOVE = "container";
    case ADD = "+container";
    case REMOVE = "-container";
    case KILL = "kill";
    case SESSION_JOIN_LEFT = "session";
    case SESSION_JOIN = "+session";
    case SESSION_LEFT = "-session";
    case CHAT = "chat";
    case COMMAND = "command";

    /**
     * @return Action[]
     */
    public function getActions(): array
    {
        return match ($this) {
            self::ALL => Action::cases(),
            self::PLACE_BREAK => [Action::PLACE, Action::BREAK, Action::SPAWN, Action::DESPAWN],
            self::PLACE => [Action::PLACE, Action::SPAWN],
            self::BREAK => [Action::BREAK, Action::DESPAWN],
            self::CLICK => [Action::CLICK],
            self::ADD_REMOVE => [Action::ADD, Action::REMOVE],
            self::ADD => [Action::ADD],
            self::REMOVE => [Action::REMOVE],
            self::KILL => [Action::KILL],
            self::SESSION_JOIN_LEFT => [Action::SESSION_JOIN, Action::SESSION_LEFT],
            self::SESSION_JOIN => [Action::SESSION_JOIN],
            self::SESSION_LEFT => [Action::SESSION_LEFT],
            self::CHAT => [Action::CHAT],
            self::COMMAND => [Action::COMMAND]
        };
    }
}