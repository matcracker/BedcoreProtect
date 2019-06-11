<?php
namespace Time\Unit\Tests;

use PHPUnit\Framework\TestCase;
use Time\Unit\TimeUnitHour;

class TimeUnitHourTest extends TestCase
{
	/**
	 * @test
	 */
	public function testToSeconds()
	{
		$this->assertEquals( 3600, TimeUnitHour::toSeconds( 1 ) );
	}

	/**
	 * @test
	 */
	public function testSleep()
	{
		TimeUnitHour::sleep( 0 );
		$this->assertTrue( true );
	}
}