<?php
namespace Time\Unit;

interface TimeUnitSecondsInterface extends TimeUnitInterface
{
	/**
	 * @param int $delay
	 * @return int
	 */
	static function toSeconds( $delay );
}