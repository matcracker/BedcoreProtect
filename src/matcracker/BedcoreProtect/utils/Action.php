<?php

declare(strict_types=1);

namespace matcracker\BedcoreProtect\utils;

use InvalidArgumentException;
use pocketmine\utils\EnumTrait;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever enum members are added, removed or changed.
 * @see EnumTrait::_generateMethodAnnotations()
 *
 * @method static self NONE()
 * @method static self PLACE()
 * @method static self BREAK()
 * @method static self CLICK()
 * @method static self KILL()
 * @method static self ADD()
 * @method static self REMOVE()
 */
final class Action{
	use EnumTrait {
		register as Enum_register;
		__construct as Enum___construct;
	}

	/** @var Action[] */
	private static $numericIdMap = [];
	/**@var int */
	private $type;
	/**@var string */
	private $message;

	public function __construct(string $enumName, int $type, string $message){
		$this->Enum___construct($enumName);
		$this->type = $type;
		$this->message = $message;
	}

	public static function fromType(int $type) : Action{
		self::checkInit();
		if(!isset(self::$numericIdMap[$type])){
			throw new InvalidArgumentException("Unknown action type $type");
		}

		return self::$numericIdMap[$type];
	}

	protected static function setup() : array{
		return [
			new Action("none", -1, "none"),
			new Action("place", 0, "placed"),
			new Action("break", 1, "broke"),
			new Action("click", 2, "clicked"),
			new Action("kill", 3, "killed"),
			new Action("add", 4, "added"),
			new Action("remove", 5, "removed")
		];
	}

	protected static function register(Action $action) : void{
		self::Enum_register($action);
		self::$numericIdMap[$action->getType()] = $action;
	}

	/**
	 * @return int
	 */
	public function getType() : int{
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function getMessage() : string{
		return $this->message;
	}

	/**
	 * @return bool
	 */
	public function isBlockAction() : bool{
		return $this->equals(self::PLACE()) || $this->equals(self::BREAK()) || $this->equals(self::CLICK());
	}

	public function isEntityAction() : bool{
		return $this->equals(self::KILL());
	}

	public function isInventoryAction() : bool{
		return $this->equals(self::ADD()) || $this->equals(self::REMOVE());
	}
}