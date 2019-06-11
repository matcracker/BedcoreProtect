<?php
namespace Time\Unit;

use Time\Unit\Exceptions\TimeUnitException;

abstract class TimeUnit
{
	const C0 = 1;
	const C1 = self::C0 * 1000;
	const C2 = self::C1 * 1000;
	const C3 = self::C2 * 1000;
	const C4 = self::C3 * 60;
	const C5 = self::C4 * 60;
	const C6 = self::C5 * 24;

	/**
	 * @param int $timeoutSeconds
	 * @param int $timeoutNanoseconds
	 * @throws TimeUnitException
	 */
	protected static function sleepFor( $timeoutSeconds, $timeoutNanoseconds )
	{
		$result = time_nanosleep( $timeoutSeconds, $timeoutNanoseconds );
		if ( $result === false )
		{
			throw new TimeUnitException( 'Sleep failed to start!' );
		}
		else if ( is_array( $result ) )
		{
			throw new TimeUnitException( 'Sleep was interrupted! Left to sleep: ' . $result['seconds '] . ' seconds and ' . $result['nanoseconds'] . ' nanoseconds.' );
		}
	}

	/**
	 * @param int $d
	 * @param int $m
	 * @param int $over
	 * @return int
	 */
	protected static function handleOverflow( $d, $m, $over )
	{
		if ( $d > $over )
		{
			return PHP_INT_MAX;
		}
		if ( $d < -$over )
		{
			return PHP_INT_MIN;
		}

		return $d * $m;
	}
}