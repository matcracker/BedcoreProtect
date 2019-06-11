<?php
namespace Time\Unit;

interface TimeUnitNanosecondsInterface extends TimeUnitInterface
{
	/**
	 * @param int $delay
	 * @return int
	 */
	static function toNanos( $delay );
}