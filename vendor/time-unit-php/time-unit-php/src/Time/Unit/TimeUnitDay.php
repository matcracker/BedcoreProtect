<?php
namespace Time\Unit;

class TimeUnitDay extends TimeUnit implements TimeUnitSecondsInterface
{
	/**
	 * @param int $delay
	 * @return int
	 */
	public static function toSeconds( $delay )
	{
		return self::handleOverflow( $delay, self::C6 / self::C3, PHP_INT_MAX / ( self::C6 / self::C3 ) );
	}

	/**
	 * @param int $timeout
	 */
	public static function sleep( $timeout )
	{
		self::sleepFor( self::toSeconds( $timeout ), 0 );
	}
}