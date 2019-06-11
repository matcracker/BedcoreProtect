<?php
namespace Time\Unit\Tests;

use PHPUnit\Framework\TestCase;
use Time\Unit\TimeUnitMinute;

class TimeUnitMinuteTest extends TestCase
{
	/**
	 * @test
	 */
	public function testToSeconds()
	{
		$this->assertEquals( 60, TimeUnitMinute::toSeconds( 1 ) );
	}

	/**
	 * @test
	 */
	public function testSleep()
	{
		TimeUnitMinute::sleep( 0 );
		$this->assertTrue( true );
	}
}