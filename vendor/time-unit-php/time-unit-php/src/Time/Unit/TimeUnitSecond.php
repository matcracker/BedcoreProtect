<?php
namespace Time\Unit;

class TimeUnitSecond extends TimeUnit implements TimeUnitSecondsInterface
{
	/**
	 * @param int $delay
	 * @return int
	 */
	static function toSeconds( $delay )
	{
		return $delay;
	}

	/**
	 * @param int $timeout
	 */
	public static function sleep( $timeout )
	{
		self::sleepFor( self::toSeconds( $timeout ), 0 );
	}
}