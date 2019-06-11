<?php
namespace Time\Unit\Tests;

use PHPUnit\Framework\TestCase;
use Time\Unit\TimeUnitNanosecond;

class TimeUnitNanosecondTest extends TestCase
{
	/**
	 * @test
	 */
	public function testToNanos()
	{
		$this->assertEquals( 1, TimeUnitNanosecond::toNanos( 1 ) );
		$this->assertEquals( 200, TimeUnitNanosecond::toNanos( 200 ) );
	}

	/**
	 * @test
	 */
	public function testSleep()
	{
		TimeUnitNanosecond::sleep( 1 );
		$this->assertTrue( true );
	}
}