<?php
namespace OregonCatholicPress\Feature\Tests;

use OregonCatholicPress\Feature\World as World;

use PHPUnit_Framework_TestCase as Test;

class FakeWorldTest extends Test
{
    public function testInGroup()
    {
        $world = new FakeWorld(['inGroup' => [0 => 1234], 'uaid' => '0']);
        $this->assertTrue($world->inGroup(0, 1234));
    }
 
    public function testNotInGroup()
    {
        $world = new FakeWorld(['inGroup' => [0 => 4321], 'uaid' => '0']);
        $this->assertFalse($world->inGroup(0, 1234));
    }

    public function testIsAdmin()
    {
        $world = new FakeWorld(['isAdmin' => true]);
        $this->assertTrue($world->isAdmin(1234));
    }

    public function testIsNotAdmin()
    {
        $world = new FakeWorld(['isAdmin' => false]);
        $this->assertFalse($world->isAdmin(0));
    }

    public function testUaid()
    {
        $world = new FakeWorld(['uaid' => '0']);
        $this->assertEquals('0', $world->uaid());
    }

    public function testUserId()
    {
        $world = new FakeWorld(['userID' => 1234]);
        $this->assertEquals(1234, $world->userId());
    }

    public function testUserName()
    {
        $world = new FakeWorld(['userName' => 'fred']);
        $this->assertEquals('fred', $world->userName(1234));
    }

    public function testIsInternalRequest()
    {
        $world = new FakeWorld(['isInternal' => true]);
        $this->assertTrue($world->isInternalRequest());
    }

    public function testUrlFeatures()
    {
        $world = new FakeWorld(['urlFeatures' => 'some-feature']);
        $this->assertEquals('some-feature', $world->urlFeatures());
    }

    public function testRandom()
    {
        $world = new FakeWorld();
        $random = $world->random();
        $this->assertGreaterThanOrEqual(0, $random);
        $this->assertLessThanOrEqual(1, $random);
        $this->assertTrue(is_float($random));
    }

    public function testHash()
    {
        $world = new FakeWorld();
        $hash = $world->hash(mt_rand());
        $this->assertGreaterThanOrEqual(0, $hash);
        $this->assertLessThan(1, $hash);
        $this->assertTrue(is_double($hash));
    }

    /** @todo Figure what selections are and then do something. */
    public function testSelections()
    {
         
    }
}
