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

use InvalidArgumentException;
use matcracker\BedcoreProtect\Main;
use pocketmine\utils\EnumTrait;
use pocketmine\utils\RegistryTrait;
use function mb_strtolower;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever enum members are added, removed or changed.
 * @see EnumTrait::_generateMethodAnnotations()
 *
 * @method static Action NONE()
 * @method static Action PLACE()
 * @method static Action BREAK()
 * @method static Action CLICK()
 * @method static Action SPAWN()
 * @method static Action DESPAWN()
 * @method static Action KILL()
 * @method static Action ADD()
 * @method static Action REMOVE()
 * @method static Action SESSION_JOIN()
 * @method static Action SESSION_LEFT()
 * @method static Action CHAT()
 * @method static Action COMMAND()
 * @method static Action UPDATE()
 */
final class ActionType
{
    use RegistryTrait;

    public const COMMAND_ARGUMENTS = [
        "all",
        "block",
        "+block",
        "-block",
        "click",
        "container",
        "+container",
        "-container",
        "kill",
        "session",
        "+session",
        "-session",
        "chat",
        "command"
    ];

    /** @var Action[] */
    private static array $idMap = [];
    /** @var array<string, Action[]> */
    private static array $commandMap = [];


    public static function fromId(int $id): Action
    {
        self::checkInit();
        return self::$idMap[$id] ?? throw new InvalidArgumentException("Unknown action type $id");
    }

    /**
     * Returns an array of Action of the specific command argument.
     * @param string $argument
     * @return Action[]|null
     */
    public static function fromCommandArgument(string $argument): ?array
    {
        self::checkInit();
        return self::$commandMap[mb_strtolower($argument)] ?? throw new InvalidArgumentException("Unknown argument $argument");
    }

    protected static function setup(): void
    {
        $lang = Main::getInstance()->getLanguage();
        self::register("place", new Action(0, $lang->translateString("action.place"), ["all", "block", "+block"]));
        self::register("break", new Action(1, $lang->translateString("action.break"), ["all", "block", "-block"]));
        self::register("click", new Action(2, $lang->translateString("action.click"), ["all", "click"]));
        self::register("spawn", new Action(3, $lang->translateString("action.place"), ["all", "block"]));
        self::register("despawn", new Action(4, $lang->translateString("action.break"), ["all", "block"]));
        self::register("kill", new Action(5, $lang->translateString("action.kill"), ["all", "kill"]));
        self::register("add", new Action(6, $lang->translateString("action.add"), ["all", "container", "+container"]));
        self::register("remove", new Action(7, $lang->translateString("action.remove"), ["all", "container", "-container"]));
        self::register("session_join", new Action(8, $lang->translateString("action.join"), ["all", "session", "+session"]));
        self::register("session_left", new Action(9, $lang->translateString("action.left"), ["all", "session", "-session"]));
        self::register("chat", new Action(10, $lang->translateString("action.chat"), ["all", "chat"]));
        self::register("command", new Action(11, $lang->translateString("action.command"), ["all", "command"]));
        self::register("update", new Action(255, "update", []));
    }

    protected static function register(string $name, Action $action): void
    {
        self::_registryRegister($name, $action);
        self::$idMap[$action->getId()] = $action;
        foreach ($action->getArguments() as $argument) {
            self::$commandMap[mb_strtolower($argument)][] = $action;
        }
    }
}
