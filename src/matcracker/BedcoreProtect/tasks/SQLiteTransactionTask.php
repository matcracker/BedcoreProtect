<?php

declare(strict_types=1);

namespace matcracker\BedcoreProtect\tasks;

use matcracker\BedcoreProtect\storage\Database;
use pocketmine\scheduler\Task;

final class SQLiteTransactionTask extends Task{

	private $database;

	public function __construct(Database $database){
		$this->database = $database;
	}

	/**
	 * Return the ticks when task is executed (5 minutes)
	 * @return int
	 */
	public final static function getTime() : int{
		return 5 * 60 * 60 * 20;
	}

	public function onRun(int $currentTick){
		$this->database->getQueries()->endTransaction();
		$this->database->getQueries()->beginTransaction();
	}

	public function onCancel(){
		$this->database->getQueries()->endTransaction();
	}
}