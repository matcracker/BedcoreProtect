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

use dktapps\pmforms\element\CustomFormElement;
use dktapps\pmforms\element\Dropdown;
use dktapps\pmforms\element\Input;
use dktapps\pmforms\element\Slider;
use InvalidArgumentException;
use matcracker\BedcoreProtect\forms\WorldDropDown;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\utils\WorldUtils;
use pocketmine\utils\EnumTrait;
use function array_map;
use function count;
use function in_array;
use function mb_strtolower;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever enum members are added, removed or changed.
 * @see EnumTrait::_generateMethodAnnotations()
 *
 * @method static self USERS()
 * @method static self TIME()
 * @method static self WORLD()
 * @method static self RADIUS()
 * @method static self ACTIONS()
 * @method static self INCLUDE ()
 * @method static self EXCLUDE()
 */
final class CommandParameter
{
    use CustomEnumTrait {
        register as Enum_register;
        __construct as Enum___construct;
        fromString as Enum_fromString;
    }

    /**
     * @param string $enumName
     * @param string[] $aliases
     * @param CustomFormElement $formElement
     * @param string $example
     */
    public function __construct(
        string                             $enumName,
        private readonly array             $aliases,
        private readonly CustomFormElement $formElement,
        private readonly string            $example)
    {
        $this->Enum___construct($enumName);
    }

    public static function fromString(string $name): ?CommandParameter
    {
        try {
            return self::Enum_fromString($name);
        } catch (InvalidArgumentException) {
            foreach (self::getAll() as $enum) {
                if (in_array(mb_strtolower($name), $enum->getAliases())) {
                    return $enum;
                }
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    public static function getAllNames(): array
    {
        return array_map(static function (CommandParameter $parameter): string {
            return $parameter->name();
        }, self::getAll());
    }

    public static function count(): int
    {
        return count(self::getAll());
    }

    protected static function setup(): void
    {
        $plugin = Main::getInstance();
        $lang = $plugin->getLanguage();
        $config = $plugin->getParsedConfig();
        $defaultRadius = $config->getDefaultRadius();

        if (($maxRadius = $config->getMaxRadius()) === 0) {
            $radiusElement = new Input(
                "radius",
                $lang->translateString("form.params.radius"),
                "5",
                $defaultRadius > 0 ? "$defaultRadius" : ""
            );
        } else {
            $radiusElement = new Slider(
                "radius",
                $lang->translateString("form.params.radius"),
                0,
                $maxRadius,
                1.0,
                $defaultRadius,
            );
        }

        self::registerAll(
            new self(
                "users",
                ["user", "u"],
                new Input(
                    "users",
                    $lang->translateString("form.params.users"),
                    $lang->translateString("form.params.users-placeholder")
                ),
                "[u=shoghicp], [u=shoghicp,#zombie]"
            ),
            new self(
                "time",
                ["t"],
                new Input(
                    "time",
                    $lang->translateString("form.params.time"),
                    "1h3m10s"
                ),
                "[t=2w5d7h2m10s], [t=5d2h]"
            ),
            new self(
                "world",
                ["w"],
                new WorldDropDown(
                    "world",
                    $lang->translateString("form.params.world"),
                    WorldUtils::getWorldNames()
                ),
                "[w=my_world], [w=faction]"
            ),
            new self(
                "radius",
                ["r"],
                $radiusElement,
                "[r=15]"
            ),
            new self(
                "actions",
                ["action", "a"],
                new Dropdown(
                    "action",
                    $lang->translateString("form.params.actions"),
                    ActionType::COMMAND_ARGUMENTS
                ),
                "[a=block], [a=+block], [a=-block], [a=click,container], [a=block,kill]"
            ),
            new self(
                "include",
                ["i"],
                new Input(
                    "inclusions",
                    $lang->translateString("form.params.include"),
                    "stone,dirt,grass,..."
                ),
                "[b=stone], [b=red_wool,dirt,tnt,...]"
            ),
            new self(
                "exclude",
                ["e"],
                new Input(
                    "exclusions",
                    $lang->translateString("form.params.exclude"),
                    "stone,dirt,grass,..."
                ),
                "[e=stone], [e=red_wool,dirt,tnt,...]"
            )
        );
    }

    protected static function register(CommandParameter $member): void
    {
        self::Enum_register($member);
    }

    public function getExample(): string
    {
        return $this->example;
    }

    public function getFormElement(): CustomFormElement
    {
        return $this->formElement;
    }
}
