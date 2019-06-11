<?php
namespace Time\Unit;

interface TimeUnitInterface
{
	/**
	 * @param int $timeout
	 */
	static function sleep( $timeout );
}