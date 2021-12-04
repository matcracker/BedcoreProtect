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
use matcracker\BedcoreProtect\Main;
use pocketmine\utils\EnumTrait;
use function array_key_exists;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever enum members are added, removed or changed.
 * @see EnumTrait::_generateMethodAnnotations()
 *
 * @method static self NONE()
 * @method static self PLACE()
 * @method static self BREAK()
 * @method static self CLICK()
 * @method static self SPAWN()
 * @method static self DESPAWN()
 * @method static self KILL()
 * @method static self ADD()
 * @method static self REMOVE()
 * @method static self UPDATE()
 */
final class Action
{
    use CustomEnumTrait {
        register as Enum_register;
        __construct as Enum___construct;
    }

    public const COMMAND_ARGUMENTS = [
        "all",
        "block",
        "+block",
        "-block",
        "click",
        "container",
        "+container",
        "-container",
        "kill"
    ];

    /** @var self[] */
    private static array $numericIdMap = [];
    /** @var self[][] */
    private static array $commandArgumentsMap;

    /**
     * Action constructor.
     * @param string $enumName
     * @param int $type
     * @param string $message
     * @param string[] $commandArguments
     */
    public function __construct(
        string         $enumName,
        private int    $type,
        private string $message,
        private array  $commandArguments
    )
    {
        $this->Enum___construct($enumName);
    }

    public static function fromType(int $type): self
    {
        self::checkInit();
        if (!array_key_exists($type, self::$numericIdMap)) {
            throw new InvalidArgumentException("Unknown action type $type");
        }

        return self::$numericIdMap[$type];
    }

    /**
     * Returns an array of Action of the specific command argument.
     * @param string $argument
     * @return self[]|null
     */
    public static function fromCommandArgument(string $argument): ?array
    {
        self::checkInit();
        return self::$commandArgumentsMap[$argument] ?? null;
    }

    protected static function setup(): void
    {
        $lang = Main::getInstance()->getLanguage();
        self::registerAll(
            new self("place", 0, $lang->translateString("action.place"), ["all", "block", "+block"]),
            new self("break", 1, $lang->translateString("action.break"), ["all", "block", "-block"]),
            new self("click", 2, $lang->translateString("action.click"), ["all", "click"]),
            new self("spawn", 3, $lang->translateString("action.place"), ["all", "block"]),
            new self("despawn", 4, $lang->translateString("action.break"), ["all", "block"]),
            new self("kill", 5, $lang->translateString("action.kill"), ["all", "kill"]),
            new self("add", 6, $lang->translateString("action.add"), ["all", "container", "+container"]),
            new self("remove", 7, $lang->translateString("action.remove"), ["all", "container", "-container"]),
            new self("update", 255, "update", [])
        );
    }

    protected static function register(self $member): void
    {
        self::Enum_register($member);
        self::$numericIdMap[$member->getType()] = $member;
        foreach ($member->getCommandArguments() as $commandArgument) {
            self::$commandArgumentsMap[$commandArgument][] = $member;
        }
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @return string[]
     */
    public function getCommandArguments(): array
    {
        return $this->commandArguments;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    public function isBlockAction(): bool
    {
        return $this->equals(self::PLACE()) || $this->equals(self::BREAK()) || $this->equals(self::CLICK());
    }

    public function isEntityAction(): bool
    {
        return $this->equals(self::SPAWN()) || $this->equals(self::DESPAWN()) || $this->equals(self::KILL());
    }

    public function isInventoryAction(): bool
    {
        return $this->equals(self::ADD()) || $this->equals(self::REMOVE());
    }
}
