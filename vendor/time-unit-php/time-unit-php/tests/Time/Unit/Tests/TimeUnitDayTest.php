<?php
namespace Time\Unit\Tests;

use PHPUnit\Framework\TestCase;
use Time\Unit\TimeUnitDay;

class TimeUnitDayTest extends TestCase
{
	/**
	 * @test
	 */
	public function testToSeconds()
	{
		$this->assertEquals( 86400, TimeUnitDay::toSeconds( 1 ) );
	}

	/**
	 * @test
	 */
	public function testSleep()
	{
		TimeUnitDay::sleep( 0 );
		$this->assertTrue( true );
	}
}