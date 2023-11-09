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
use matcracker\BedcoreProtect\forms\WorldDropDown;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\utils\WorldUtils;
use function in_array;

enum CommandParameter: string
{
    use NamedEnumTrait;
    use ValueBackedEnumTrait;

    public const WILDCARD_CHAR = "#";

    case USERS = "users";
    case TIME = "time";
    case WORLD = "world";
    case RADIUS = "radius";
    case ACTIONS = "actions";
    case INCLUDE = "include";
    case EXCLUDE = "exclude";

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return match ($this) {
            self::USERS => ["user", "u"],
            self::TIME => ["t"],
            self::WORLD => ["w"],
            self::RADIUS => ["r"],
            self::ACTIONS => ["action", "a"],
            self::INCLUDE => ["i"],
            self::EXCLUDE => ["e"]
        };
    }

    public function getFormElement(): CustomFormElement
    {
        $plugin = Main::getInstance();
        $lang = $plugin->getLanguage();
        $config = $plugin->getParsedConfig();
        $defaultRadius = $config->getDefaultRadius();
        $text = $lang->translateString("form.params.$this->value");

        if (($maxRadius = $config->getMaxRadius()) === 0) {
            $radiusElement = new Input(
                $this->value,
                $text,
                "5",
                $defaultRadius > 0 ? "$defaultRadius" : ""
            );
        } else {
            $radiusElement = new Slider(
                $this->value,
                $text,
                0,
                $maxRadius,
                1.0,
                $defaultRadius,
            );
        }

        return match ($this) {
            self::USERS => new Input(
                $this->value,
                $text,
                $lang->translateString("form.params.users-placeholder")
            ),
            self::TIME => new Input(
                $this->value,
                $text,
                "1h3m10s"
            ),
            self::WORLD => new WorldDropDown(
                $this->value,
                $text,
                WorldUtils::getWorldNames()
            ),
            self::RADIUS => $radiusElement,
            self::ACTIONS => new Dropdown(
                $this->value,
                $text,
                ActionCommandArgument::getValues()
            ),
            self::INCLUDE, self::EXCLUDE => new Input(
                $this->value,
                $text,
                "stone,dirt,grass,..."
            )
        };
    }

    public function getExample(): string
    {
        return match ($this) {
            self::USERS => "[u=shoghicp], [u=shoghicp,#zombie,...]",
            self::TIME => "[t=2w5d7h2m10s], [t=5d2h]",
            self::WORLD => "[w=my_world], [w=faction]",
            self::RADIUS => "[r=15]",
            self::ACTIONS => "[a=block], [a=+block], [a=-block], [a=click,container], [a=block,kill]",
            self::INCLUDE => "[i=stone], [i=red_wool,dirt,tnt,...]",
            self::EXCLUDE => "[e=stone], [e=red_wool,dirt,tnt,...]"
        };
    }

    public static function tryFromAlias(string $value): ?static
    {
        $enum = self::tryFrom($value);

        if ($enum === null) {
            foreach (CommandParameter::cases() as $parameter) {
                if (in_array($value, $parameter->getAliases(), true)) {
                    return $parameter;
                }
            }
            return null;
        } else {
            return $enum;
        }
    }
}
