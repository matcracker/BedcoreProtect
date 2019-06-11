<?php
namespace Time\Unit\Tests;

use PHPUnit\Framework\TestCase;
use Time\Unit\TimeUnitMicrosecond;

class TimeUnitMicrosecondTest extends TestCase
{
	/**
	 * @test
	 */
	public function testToNanos()
	{
		$this->assertEquals( 1000, TimeUnitMicrosecond::toNanos( 1 ) );
		$this->assertEquals( 200000, TimeUnitMicrosecond::toNanos( 200 ) );
	}

	/**
	 * @test
	 */
	public function testSleep()
	{
		TimeUnitMicrosecond::sleep( 1 );
		$this->assertTrue( true );
	}
}