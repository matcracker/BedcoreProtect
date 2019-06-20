<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
 *
 * Copyright (C) 2019
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

namespace matcracker\BedcoreProtect\storage;

use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\storage\queries\Queries;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;

class Database{
	/**@var DataConnector */
	private $database;
	/**@var Queries */
	private $queries;

	public function __construct(Main $plugin){
		$this->database = libasynql::create($plugin, $plugin->getConfig()->get("database"), [
			"sqlite" => "sqlite.sql",
			"mysql" => "mysql.sql"
		]);
		$this->queries = new Queries($this->database, $plugin->getParsedConfig());
	}

	public function getQueries() : Queries{
		return $this->queries;
	}

	public final function close() : void{
		if($this->isConnected()){
			$this->database->close();
		}
	}

	public final function isConnected() : bool{
		return isset($this->database);
	}
}