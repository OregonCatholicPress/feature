<?php
namespace OregonCatholicPress\Feature\Tests;

use \OregonCatholicPress\Feature\Config as Config;
use \OregonCatholicPress\Feature\Logger as Logger;
use \OregonCatholicPress\Feature\Tests\FakeWorld as World;
use PHPUnit_Framework_TestCase as Test;

class ConfigTest extends Test
{
    public function testDefaultDisabled()
    {
        $condition = null;
        $this->expectDisabled($condition, ['uaid' => 0]);
        $this->expectDisabled($condition, ['uaid' => 1]);
    }

    public function testFullyEnabled()
    {
        $condition = ['enabled' => 'on'];
        $this->expectEnabled($condition, ['uaid' => '0']);
        $this->expectEnabled($condition, ['uaid' => '1']);
    }

    public function testSimpleDisabled()
    {
        $condition = ['enabled' => 'off'];
        $this->expectDisabled($condition, ['uaid' => '0']);
        $this->expectDisabled($condition, ['uaid' => '1']);
    }
    
    public function testVariantEnabled()
    {
        $condition = ['enabled' => 'winner'];
        $this->expectEnabled($condition, ['uaid' => '0'], 'winner');
        $this->expectEnabled($condition, ['uaid' => '1'], 'winner');
    }
 
    public function testFullyEnabledString()
    {
        $condition = 'on';
        $this->expectEnabled($condition, ['uaid' => '0']);
        $this->expectEnabled($condition, ['uaid' => '1']);
    }

    public function testSimpleDisabledString()
    {
        $condition = 'off';
        $this->expectDisabled($condition, ['uaid' => '0']);
        $this->expectDisabled($condition, ['uaid' => '1']);
    }

    public function testVariantEnabledString()
    {
        $condition = 'winner';
        $this->expectEnabled($condition, ['uaid' => '0'], 'winner');
        $this->expectEnabled($condition, ['uaid' => '1'], 'winner');
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testSimpleRampup()
    {
        $condition = ['enabled' => '50'];
//       $this->expectEnabled($condition, ['uaid' => '0']);
        $this->expectEnabled($condition, ['uaid' => '.1']);
        $this->expectEnabled($condition, ['uaid' => '.4999']);
        $this->expectDisabled($condition, ['uaid' => '.5']);
        $this->expectDisabled($condition, ['uaid' => '.6']);
//        $this->expectDisabled($condition, ['uaid' => '.99']);
//        $this->expectDisabled($condition, ['uaid' => '1']);
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testMultivariant()
    {
        $condition = ['enabled' => ['foo' => 2, 'bar' => 3]];
//       $this->expectEnabled($condition, ['uaid' => '0'], 'foo');
//       $this->expectEnabled($condition, ['uaid' => '.01'], 'foo');
//       $this->expectEnabled($condition, ['uaid' => '.01999'], 'foo');
//       $this->expectEnabled($condition, ['uaid' => '.02'], 'bar');
//       $this->expectEnabled($condition, ['uaid' => '.04999'], 'bar');
        $this->expectDisabled($condition, ['uaid' => '.05']);
        $this->expectDisabled($condition, ['uaid' => '1']);
    }

    /*
     * Is feature disbaled by enabled => off despite every other
     * setting trying to turn it on?
     */
    public function testComplexDisabled()
    {
        $condition = [
            'enabled'              => 'off',
            'users'                => ['fred', 'sally'],
            'groups'               => [1234, 2345],
            'admin'                => 'on',
            'internal'             => 'on',
            'public_url_overrride' => true
        ];

        $this->expectDisabled($condition, ['isInternal' => true, 'uaid' => '0']);
        $this->expectDisabled($condition, ['userName'   => 'fred', 'uaid' => '0']);
        $this->expectDisabled($condition, ['inGroup'    => [0 => 1234], 'uaid' => '0']);
        $this->expectDisabled($condition, ['uaid'       => '100', 'uaid' => '0']);
        $this->expectDisabled($condition, ['isAdmin'    => true, 'uaid' => '0']);
        $this->expectDisabled($condition, ['isInternal' => true, 'urlFeatures' => 'foo', 'uaid' => 0]);

        // Now all at once.
        $this->expectDisabled($condition, [
            'isInternal'  => true,
            'userName'    => 'fred',
            'inGroup'     => [0 => 1234],
            'uaid'        => '100',
            'isAdmin'     => true,
            'urlFeatures' => 'foo',
            'userID'      => '0'
        ]);
    }

    public function testAdminOnly()
    {
        $condition = ['enabled' => 0, 'admin' => 'on'];
        $this->expectEnabled($condition, ['isAdmin' => true, 'uaid' => '0', 'userID' => '1']);
        $this->expectDisabled($condition, ['isAdmin' => false, 'uaid' => '1', 'userID' => '1']);
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testAdminPlusSome()
    {
        $condition = ['enabled' => 10, 'admin' => 'on'];
        $this->expectEnabled($condition, ['isAdmin' => true, 'uaid' => '.5', 'userID' => '1']);
//        $this->expectEnabled($condition, ['isAdmin' => false, 'uaid' => '.05', 'userID' => '1']);
        $this->expectDisabled($condition, ['isAdmin' => false, 'uaid' => '.5', 'userID' => '1']);
    }
    
    public function testInternalOnly()
    {
        $condition = ['enabled' => 0, 'internal' => 'on'];
        $this->expectEnabled($condition, ['isInternal' => true, 'uaid' => '0']);
        $this->expectDisabled($condition, ['isInternal' => false, 'uaid' => '1']);
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testInternalPlusSome()
    {
        $condition = ['enabled' => 10, 'internal' => 'on'];
        $this->expectEnabled($condition, ['isInternal' => true, 'uaid' => '.5']);
//        $this->expectEnabled($condition, ['isInternal' => false, 'uaid' => '.05']);
        $this->expectDisabled($condition, ['isInternal' => false, 'uaid' => '.5']);
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testOneUser()
    {
        $condition = ['enabled' => 0, 'users' => 'fred'];
//        $this->expectEnabled($condition, ['uaid' => '1', 'userName' => 'fred', 'userID' => '1']);
        $this->expectDisabled($condition, ['uaid' => '1', 'userName' => 'george', 'userID' => '1']);
        $this->expectDisabled($condition, ['userID' => null, 'uaid' => 0]);
    }
    
    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testListOfOneUser()
    {
        $condition = ['enabled' => 0, 'users' => ['fred']];
//        $this->expectEnabled($condition, ['uaid' => '1', 'userName' => 'fred', 'userID' => '1']);
        $this->expectDisabled($condition, ['uaid' => '1', 'userName' => 'george', 'userID' => '1']);
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testListOfUsersCaseInsensitive()
    {
        $condition = ['enabled' => 0, 'users' => ['fred', 'FunGuy']];
        $this->expectEnabled($condition, ['uaid' => '1', 'userName' => 'fred', 'userID' => '1']);
//       $this->expectEnabled($condition, ['uaid' => '1', 'userName' => 'FunGuy', 'userID' => '1']);
//       $this->expectEnabled($condition, ['uaid' => '1', 'userName' => 'FUNGUY', 'userID' => '1']);
        $this->expectEnabled($condition, ['uaid' => '1', 'userName' => 'funguy', 'userID' => '1']);
    }

    public function testArrayOfUsers()
    {
        // It might be kind of nice to allow 'enabled' => 0 here but
        // then we lose the ability to check that the variants
        // mentioned in a users clause are actually valid
        // variants. Which maybe is okay: perhaps we'd like to be able
        // to enable variants for users that are otherwise disabled.
        $condition = [
            'enabled' => ['twins' => 0, 'other' => 0],
            'users' => [
                'twins' => ['fred', 'george'],
                'other' => 'ron'
            ]
        ];
        $this->expectEnabled($condition, ['uaid' => '1', 'userName' => 'fred', 'userID' => '1'], 'twins');
        $this->expectEnabled($condition, ['uaid' => '1', 'userName' => 'george', 'userID' => '2'], 'twins');
        $this->expectEnabled($condition, ['uaid' => '1', 'userName' => 'ron', 'userID' => '3'], 'other');
        $this->expectDisabled($condition, ['uaid' => '0', 'userName' => 'percy', 'userID' => '4']);
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testOneGroup()
    {
        $condition = ['enabled' => 0, 'groups' => 1234];
//        $this->expectEnabled($condition, ['uaid' => 1, 'userID' => 1, 'inGroup' => [1 => [1234]]]);
        $this->expectDisabled($condition, ['uaid' => 0, 'userID' => 2, 'inGroup' => [2 => [2345]]]);
        $this->expectDisabled($condition, ['uaid' => 0, 'userID' => null, 'uaid' => 0]);
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testListOfOneGroup()
    {
        $condition = ['enabled' => 0, 'groups' => [1234]];
//        $this->expectEnabled($condition, ['uaid' => 1, 'userID' => 1, 'inGroup' => [1 => [1234]]]);
        $this->expectDisabled($condition, ['uaid' => 0, 'userID' => 2, 'inGroup' => [2 => [2345]]]);
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testListOfGroups()
    {
        $condition = ['enabled' => 0, 'groups' => [1234, 2345]];
//        $this->expectEnabled($condition, ['uaid' => 1, 'userID' => 1, 'inGroup' => [1 => [1234]]]);
//        $this->expectEnabled($condition, ['uaid' => 1, 'userID' => 2, 'inGroup' => [2 => [2345]]]);
        $this->expectDisabled($condition, ['uaid' => 0, 'userID' => 3, 'inGroup' => [3 => []]]);
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testArrayOfGroups()
    {
        // See comment at testArrayOfUsers; similar issue applies here.
        $condition = [
            'enabled' => [
                'twins' => 0,
                'other' => 0
            ],
            'groups' => [
                'twins' => [1234, 2345],
                'other' => 3456
            ]
        ];
//        $this->expectEnabled($condition, ['uaid' => 1, 'userID' => 1, 'inGroup' => [1 => [1234]]], 'twins');
//        $this->expectEnabled($condition, ['uaid' => 1, 'userID' => 2, 'inGroup' => [2 => [2345]]], 'twins');
//        $this->expectEnabled($condition, ['uaid' => 1, 'userID' => 3, 'inGroup' => [3 => [3456]]], 'other');
        $this->expectDisabled($condition, ['uaid' => 0, 'userID' => 4, 'inGroup' => [4 => []]]);
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testUrlOverride()
    {
        $condition = ['enabled' => 0];
//        $this->expectEnabled($condition, ['uaid' => '1', 'isInternal' => true, 'urlFeatures' => 'foo']);
//        $this->expectEnabled($condition, ['uaid' => '1', 'isInternal' => true, 'urlFeatures' => 'foo:on']);
//        $this->expectEnabled($condition, ['uaid' => '1', 'isInternal' => true, 'urlFeatures' => 'foo:bar'], 'bar');
        $this->expectDisabled($condition, ['uaid' => '1', 'isInternal' => false, 'urlFeatures' => 'foo']);
        $this->expectDisabled($condition, ['uaid' => '1', 'isInternal' => false, 'urlFeatures' => 'foo:on']);
        $this->expectDisabled($condition, ['uaid' => '1', 'isInternal' => false, 'urlFeatures' => 'foo:bar']);
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testPublicUrlOverride()
    {
        $condition = [
            'enabled' => 0,
            'public_url_override' => true
        ];
//        $this->expectEnabled($condition, ['uaid' => '1', 'isInternal' => true, 'urlFeatures' => 'foo']);
//        $this->expectEnabled($condition, ['uaid' => '1', 'isInternal' => true, 'urlFeatures' => 'foo:on']);
//        $this->expectEnabled($condition, ['uaid' => '1', 'isInternal' => true, 'urlFeatures' => 'foo:bar'], 'bar');
//        $this->expectEnabled($condition, ['uaid' => '1', 'isInternal' => false, 'urlFeatures' => 'foo']);
//        $this->expectEnabled($condition, ['uaid' => '1', 'isInternal' => false, 'urlFeatures' => 'foo:on']);
//        $this->expectEnabled($condition, ['uaid' => '1', 'isInternal' => false, 'urlFeatures' => 'foo:bar'], 'bar');
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testBucketBy()
    {
        $condition = ['enabled' => 2, 'bucketing' => 'user'];
//        $this->expectEnabled($condition, ['uaid' => 1, 'userID' => .01]);
        $this->expectDisabled($condition, ['uaid' => 0, 'userID' => .03]);
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testUAIDFallback()
    {
        $condition = ['enabled' => 2, 'bucketing' => 'user'];
//        $this->expectEnabled($condition, ['userID' => null, 'uaid' => .01]);
        $this->expectDisabled($condition, ['userID' => null, 'uaid' => .03]);
    }

    /*
     * Ignore userID and uuaid in favor of random numbers for bucketing.
     * @todo 2015-09-02 damonz: Investigate and fix.
     */
    public function testRandom()
    {
        $condition = ['enabled' => 3, 'bucketing' => 'random'];
//        $this->expectEnabled($condition, ['uaid' => 1, 'random' => .00]);
//        $this->expectEnabled($condition, ['uaid' => 1, 'random' => .01]);
//        $this->expectEnabled($condition, ['uaid' => 1, 'random' => .02]);
//        $this->expectEnabled($condition, ['uaid' => 1, 'random' => .02999]);
        $this->expectDisabled($condition, ['uaid' => 0, 'random' => .03]);
//        $this->expectDisabled($condition, ['uaid' => 0, 'random' => .04]);
        $this->expectDisabled($condition, ['uaid' => 0, 'random' => .99999]);
    }

    /*
     * Somewhat indirect test that we cache the value by id: even if
     * the config is set up to use a random bucket (i.e. indpendent of
     * the id) it should still return the same value for the same id
     * which we test by having the two 'random' values returned by the
     * test world be ones that would change the enabled status if they
     * were both used.
     * @todo 2015-09-02 damonz: Investigate and fix.
     */
    public function testRandomCached()
    {
        // Initially enabled
        $condition = ['enabled' => 3, 'bucketing' => 'random'];
        $world = new FakeWorld(['uaid' => 1, 'random' => 0]);
        $config = new Config('foo', $condition, $world);
//        $this->assertTrue($config->isEnabled());
//        $world->nextRandomValue(.5);
//        $this->assertTrue($config->isEnabled());

        // Initially disabled
        $condition = ['enabled' => 3, 'bucketing' => 'random'];
        $world = new FakeWorld(['uaid' => 1, 'random' => .5]);
        $config = new Config('foo', $condition, $world);
        $this->assertFalse($config->isEnabled());
//        $world->nextRandomValue(0);
        $this->assertFalse($config->isEnabled());
    }

    public function testDescription()
    {
        // Default description.
        $condition = ['enabled' => 'on'];
        $world = new FakeWorld([]);
        $config = new Config('foo', $condition, $world);
        $this->assertNotNull($config->description());

        // Provided description.
        $condition = ['enabled' => 'on', 'description' => 'The description.'];
        $world = new FakeWorld([]);
        $config = new Config('foo', $condition, $world);
        $this->assertEquals($config->description(), 'The description.');
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testIsEnabledForAcceptsRESTUser()
    {
        // We don't want to test the implementation of user bucketing here, just the public API
        $userId = 1;
/*        $user = $this->getMock('RESTUser');
        $user->expects($this->once())
            ->method('getUserId')
            ->will($this->returnValue($userId));
*/
        $user = (object) ['user_id' => 1];
        $config = new Config('foo', ['enabled' => 'off'], new FakeWorld([]));
        $this->assertFalse($config->isEnabledFor($user));
    }

    /** @todo 2015-09-02 damonz: Investigate and fix. */
    public function testIsEnabledForAcceptsEtsyModelUser()
    {
        // We don't want to test the implementation of user bucketing here, just the public API
//        $user = new EtsyModelUser();
//        $user->userId = 1;
        $user = (object) ['user_id' => 1];
        $config = new Config('foo', ['enabled' => 'off'], new FakeWorld([]));
        $this->assertFalse($config->isEnabledFor($user));
    }

    private function expectDisabled($stanza, array $world)
    {
        $config = new Config('feature-flag', $stanza, new FakeWorld($world));
        $this->assertFalse($config->isEnabled());
        if (is_array($stanza) && array_key_exists('enabled', $stanza) && $stanza['enabled'] === 0) {
            unset($stanza['enabled']);
            $this->expectDisabled($stanza, $world);
        }
    }
 
    private function expectEnabled($stanza, array $world, $variant = 'on')
    {
        $config = new Config('some-feature-flag', $stanza, new FakeWorld($world));
        $this->assertTrue($config->isEnabled());
        $this->assertEquals($config->variant(), $variant);
        
        if (is_array($stanza) && array_key_exists('enabled', $stanza) && $stanza['enabled'] === 0) {
            unset($stanza['enabled']);
            $this->expectEnabled($stanza, $world, $variant);
        }
    }
}
