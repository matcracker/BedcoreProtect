<?php
namespace Time\Unit\Tests;

use PHPUnit\Framework\TestCase;
use Time\Unit\TimeUnitSecond;

class TimeUnitSecondTest extends TestCase
{
	/**
	 * @test
	 */
	public function testToNanos()
	{
		$this->assertEquals( 1, TimeUnitSecond::toSeconds( 1 ) );
		$this->assertEquals( 200, TimeUnitSecond::toSeconds( 200 ) );
	}

	/**
	 * @test
	 */
	public function testSleep()
	{
		TimeUnitSecond::sleep( 1 );
		$this->assertTrue( true );
	}
}