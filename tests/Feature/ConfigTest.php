<?php
namespace Feature;

// require_once "Loader.php";

/*
 * Test cases:
 *
 * enabled: on, off, number, array
 * users: none, single, list, array
 * groups: none, single, list, array
 * admin: variant
 * internal: variant
 * public_url_overrride: absent, true, false
 * bucketing: 'user', 'uaid', 'random'
 */
class ConfigTest extends PHPUnit_Framework_TestCase
{

    public function testDefaultDisabled()
    {
        $condition = null;
        $this->expectDisabled($condition, array('uaid' => 0));
        $this->expectDisabled($condition, array('uaid' => 1));
    }

    public function testFullyEnabled()
    {
        $condition = array('enabled' => 'on');
        $this->expectEnabled($condition, array('uaid' => '0'));
        $this->expectEnabled($condition, array('uaid' => '1'));
    }

    public function testSimpleDisabled()
    {
        $condition = array('enabled' => 'off');
        $this->expectDisabled($condition, array('uaid' => '0'));
        $this->expectDisabled($condition, array('uaid' => '1'));
    }

    public function testVariantEnabled()
    {
        $condition = array('enabled' => 'winner');
        $this->expectEnabled($condition, array('uaid' => '0'), 'winner');
        $this->expectEnabled($condition, array('uaid' => '1'), 'winner');
    }

    public function testFullyEnabledString()
    {
        $condition = 'on';
        $this->expectEnabled($condition, array('uaid' => '0'));
        $this->expectEnabled($condition, array('uaid' => '1'));
    }

    public function testSimpleDisabledString()
    {
        $condition = 'off';
        $this->expectDisabled($condition, array('uaid' => '0'));
        $this->expectDisabled($condition, array('uaid' => '1'));
    }

    public function testVariantEnabledString()
    {
        $condition = 'winner';
        $this->expectEnabled($condition, array('uaid' => '0'), 'winner');
        $this->expectEnabled($condition, array('uaid' => '1'), 'winner');
    }

    public function testSimpleRampup()
    {
        $condition = array('enabled' => '50');
        $this->expectEnabled($condition, array('uaid' => '0'));
        $this->expectEnabled($condition, array('uaid' => '.1'));
        $this->expectEnabled($condition, array('uaid' => '.4999'));
        $this->expectDisabled($condition, array('uaid' => '.5'));
        $this->expectDisabled($condition, array('uaid' => '.6'));
        $this->expectDisabled($condition, array('uaid' => '.99'));
        $this->expectDisabled($condition, array('uaid' => '1'));
    }

    public function testMultivariant()
    {
        $condition = array('enabled' => array('foo' => 2, 'bar' => 3));
        $this->expectEnabled($condition, array('uaid' => '0'), 'foo');
        $this->expectEnabled($condition, array('uaid' => '.01'), 'foo');
        $this->expectEnabled($condition, array('uaid' => '.01999'), 'foo');
        $this->expectEnabled($condition, array('uaid' => '.02'), 'bar');
        $this->expectEnabled($condition, array('uaid' => '.04999'), 'bar');
        $this->expectDisabled($condition, array('uaid' => '.05'));
        $this->expectDisabled($condition, array('uaid' => '1'));
    }

    /*
     * Is feature disbaled by enabled => off despite every other
     * setting trying to turn it on?
     */
    public function testComplexDisabled()
    {
        $condition = array(
            'enabled'              => 'off',
            'users'                => array('fred', 'sally'),
            'groups'               => array(1234, 2345),
            'admin'                => 'on',
            'internal'             => 'on',
            'public_url_overrride' => true
        );

        $this->expectDisabled($condition, array('isInternal' => true, 'uaid' => '0'));
        $this->expectDisabled($condition, array('userName'   => 'fred', 'uaid' => '0'));
        $this->expectDisabled($condition, array('inGroup'    => array(0 => 1234), 'uaid' => '0'));
        $this->expectDisabled($condition, array('uaid'       => '100', 'uaid' => '0'));
        $this->expectDisabled($condition, array('isAdmin'    => true, 'uaid' => '0'));
        $this->expectDisabled($condition, array('isInternal' => true, 'urlFeatures' => 'foo', 'uaid' => 0));

        // Now all at once.
        $this->expectDisabled($condition, array(
            'isInternal'  => true,
            'userName'    => 'fred',
            'inGroup'     => array(0 => 1234),
            'uaid'        => '100',
            'isAdmin'     => true,
            'urlFeatures' => 'foo',
            'userID'      => '0'));
    }

    public function testAdminOnly()
    {
        $condition = array('enabled' => 0, 'admin' => 'on');
        $this->expectEnabled($condition, array('isAdmin' => true, 'uaid' => '0', 'userID' => '1'));
        $this->expectDisabled($condition, array('isAdmin' => false, 'uaid' => '1', 'userID' => '1'));
    }

    public function testAdminPlusSome()
    {
        $condition = array('enabled' => 10, 'admin' => 'on');
        $this->expectEnabled($condition, array('isAdmin' => true, 'uaid' => '.5', 'userID' => '1'));
        $this->expectEnabled($condition, array('isAdmin' => false, 'uaid' => '.05', 'userID' => '1'));
        $this->expectDisabled($condition, array('isAdmin' => false, 'uaid' => '.5', 'userID' => '1'));
    }

    public function testInternalOnly()
    {
        $condition = array('enabled' => 0, 'internal' => 'on');
        $this->expectEnabled($condition, array('isInternal' => true, 'uaid' => '0'));
        $this->expectDisabled($condition, array('isInternal' => false, 'uaid' => '1'));
    }

    public function testInternalPlusSome()
    {
        $condition = array('enabled' => 10, 'internal' => 'on');
        $this->expectEnabled($condition, array('isInternal' => true, 'uaid' => '.5'));
        $this->expectEnabled($condition, array('isInternal' => false, 'uaid' => '.05'));
        $this->expectDisabled($condition, array('isInternal' => false, 'uaid' => '.5'));
    }

    public function testOneUser()
    {
        $condition = array('enabled' => 0, 'users' => 'fred');
        $this->expectEnabled($condition, array('uaid' => '1', 'userName' => 'fred', 'userID' => '1'));
        $this->expectDisabled($condition, array('uaid' => '1', 'userName' => 'george', 'userID' => '1'));
        $this->expectDisabled($condition, array('userID' => null, 'uaid' => 0));
    }

    public function testListOfOneUser()
    {
        $condition = array('enabled' => 0, 'users' => array('fred'));
        $this->expectEnabled($condition, array('uaid' => '1', 'userName' => 'fred', 'userID' => '1'));
        $this->expectDisabled($condition, array('uaid' => '1', 'userName' => 'george', 'userID' => '1'));
    }

    public function testListOfUsers()
    {
        $condition = array('enabled' => 0, 'users' => array('fred', 'ron'));
        $this->expectEnabled($condition, array('uaid' => '1', 'userName' => 'fred', 'userID' => '1'));
        $this->expectEnabled($condition, array('uaid' => '1', 'userName' => 'ron', 'userID' => '1'));
        $this->expectDisabled($condition, array('uaid' => '1', 'userName' => 'george', 'userID' => '1'));
    }
    
    public function testListOfUsersCaseInsensitive()
    {
        $condition = array('enabled' => 0, 'users' => array('fred', 'FunGuy'));
        $this->expectEnabled($condition, array('uaid' => '1', 'userName' => 'fred', 'userID' => '1'));
        $this->expectEnabled($condition, array('uaid' => '1', 'userName' => 'FunGuy', 'userID' => '1'));
        $this->expectEnabled($condition, array('uaid' => '1', 'userName' => 'FUNGUY', 'userID' => '1'));
        $this->expectEnabled($condition, array('uaid' => '1', 'userName' => 'funguy', 'userID' => '1'));
    }

    public function testArrayOfUsers()
    {
        // It might be kind of nice to allow 'enabled' => 0 here but
        // then we lose the ability to check that the variants
        // mentioned in a users clause are actually valid
        // variants. Which maybe is okay: perhaps we'd like to be able
        // to enable variants for users that are otherwise disabled.
        $condition = array('enabled' => array('twins' => 0, 'other' => 0),
                   'users' => array(
                                    'twins' => array('fred', 'george'),
                                    'other' => 'ron'));
        $this->expectEnabled($condition, array('uaid' => '1', 'userName' => 'fred', 'userID' => '1'), 'twins');
        $this->expectEnabled($condition, array('uaid' => '1', 'userName' => 'george', 'userID' => '2'), 'twins');
        $this->expectEnabled($condition, array('uaid' => '1', 'userName' => 'ron', 'userID' => '3'), 'other');
        $this->expectDisabled($condition, array('uaid' => '0', 'userName' => 'percy', 'userID' => '4'));
    }

    public function testOneGroup()
    {
        $condition = array('enabled' => 0, 'groups' => 1234);
        $this->expectEnabled($condition, array('uaid' => 1, 'userID' => 1, 'inGroup' => array(1 => array(1234))));
        $this->expectDisabled($condition, array('uaid' => 0, 'userID' => 2, 'inGroup' => array(2 => array(2345))));
        $this->expectDisabled($condition, array('uaid' => 0, 'userID' => null, 'uaid' => 0));
    }

    public function testListOfOneGroup()
    {
        $condition = array('enabled' => 0, 'groups' => array(1234));
        $this->expectEnabled($condition, array('uaid' => 1, 'userID' => 1, 'inGroup' => array(1 => array(1234))));
        $this->expectDisabled($condition, array('uaid' => 0, 'userID' => 2, 'inGroup' => array(2 => array(2345))));
    }

    public function testListOfGroups()
    {
        $condition = array('enabled' => 0, 'groups' => array(1234, 2345));
        $this->expectEnabled($condition, array('uaid' => 1, 'userID' => 1, 'inGroup' => array(1 => array(1234))));
        $this->expectEnabled($condition, array('uaid' => 1, 'userID' => 2, 'inGroup' => array(2 => array(2345))));
        $this->expectDisabled($condition, array('uaid' => 0, 'userID' => 3, 'inGroup' => array(3 => array())));
    }

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
        $this->expectEnabled(
            $condition,
            ['uaid' => 1, 'userID' => 1, 'inGroup' => [1 => [1234]]],
            'twins'
        );
        $this->expectEnabled(
            $condition,
            ['uaid' => 1, 'userID' => 2, 'inGroup' => [2 => [2345]]],
            'twins'
        );
        $this->expectEnabled(
            $condition,
            ['uaid' => 1, 'userID' => 3, 'inGroup' => [3 => [3456]]],
            'other'
        );
        $this->expectDisabled(
            $condition,
            ['uaid' => 0, 'userID' => 4, 'inGroup' => [4 => []]]
        );
    }

    public function testUrlOverride()
    {
        $condition = array('enabled' => 0);
        $this->expectEnabled($condition, array('uaid' => '1', 'isInternal' => true, 'urlFeatures' => 'foo'));
        $this->expectEnabled($condition, array('uaid' => '1', 'isInternal' => true, 'urlFeatures' => 'foo:on'));
        $this->expectEnabled($condition, array('uaid' => '1', 'isInternal' => true, 'urlFeatures' => 'foo:bar'), 'bar');
        $this->expectDisabled($condition, array('uaid' => '1', 'isInternal' => false, 'urlFeatures' => 'foo'));
        $this->expectDisabled($condition, array('uaid' => '1', 'isInternal' => false, 'urlFeatures' => 'foo:on'));
        $this->expectDisabled($condition, array('uaid' => '1', 'isInternal' => false, 'urlFeatures' => 'foo:bar'));
    }

    public function testPublicUrlOverride()
    {
        $condition = [
            'enabled' => 0,
            'public_url_override' => true
        ];
        $this->expectEnabled($condition, array('uaid' => '1', 'isInternal' => true, 'urlFeatures' => 'foo'));
        $this->expectEnabled($condition, array('uaid' => '1', 'isInternal' => true, 'urlFeatures' => 'foo:on'));
        $this->expectEnabled($condition, array('uaid' => '1', 'isInternal' => true, 'urlFeatures' => 'foo:bar'), 'bar');
        $this->expectEnabled($condition, array('uaid' => '1', 'isInternal' => false, 'urlFeatures' => 'foo'));
        $this->expectEnabled($condition, array('uaid' => '1', 'isInternal' => false, 'urlFeatures' => 'foo:on'));
        $this->expectEnabled(
            $condition,
            ['uaid' => '1', 'isInternal' => false, 'urlFeatures' => 'foo:bar'],
            'bar'
        );
    }

    public function testBucketBy()
    {
        $condition = array('enabled' => 2, 'bucketing' => 'user');
        $this->expectEnabled($condition, array('uaid' => 1, 'userID' => .01));
        $this->expectDisabled($condition, array('uaid' => 0, 'userID' => .03));
    }

    public function testUAIDFallback()
    {
        $condition = array('enabled' => 2, 'bucketing' => 'user');
        $this->expectEnabled($condition, array('userID' => null, 'uaid' => .01));
        $this->expectDisabled($condition, array('userID' => null, 'uaid' => .03));
    }

    /*
     * Ignore userID and uuaid in favor of random numbers for bucketing.
     */
    public function testRandom()
    {
        $condition = array('enabled' => 3, 'bucketing' => 'random');
        $this->expectEnabled($condition, array('uaid' => 1, 'random' => .00));
        $this->expectEnabled($condition, array('uaid' => 1, 'random' => .01));
        $this->expectEnabled($condition, array('uaid' => 1, 'random' => .02));
        $this->expectEnabled($condition, array('uaid' => 1, 'random' => .02999));
        $this->expectDisabled($condition, array('uaid' => 0, 'random' => .03));
        $this->expectDisabled($condition, array('uaid' => 0, 'random' => .04));
        $this->expectDisabled($condition, array('uaid' => 0, 'random' => .99999));
    }

    /*
     * Somewhat indirect test that we cache the value by id: even if
     * the config is set up to use a random bucket (i.e. indpendent of
     * the id) it should still return the same value for the same id
     * which we test by having the two 'random' values returned by the
     * test world be ones that would change the enabled status if they
     * were both used.
     */
    public function testRandomCached()
    {
        // Initially enabled
        $condition = array('enabled' => 3, 'bucketing' => 'random');
        $world = new Testing_Feature_MockWorld(array('uaid' => 1, 'random' => 0));
        $config = new Feature_Config('foo', $condition, $world);
        $this->assertTrue($config->isEnabled());
        $w->nextRandomValue(.5);
        $this->assertTrue($config->isEnabled());

        // Initially disabled
        $condition = array('enabled' => 3, 'bucketing' => 'random');
        $world = new Testing_Feature_MockWorld(array('uaid' => 1, 'random' => .5));
        $config = new Feature_Config('foo', $condition, $world);
        $this->assertFalse($config->isEnabled());
        $w->nextRandomValue(0);
        $this->assertFalse($config->isEnabled());
    }

    public function testDescription()
    {
        // Default description.
        $condition = array('enabled' => 'on');
        $world = new Testing_Feature_MockWorld(array());
        $config = new Feature_Config('foo', $condition, $world);
        $this->assertNotNull($config->description());

        // Provided description.
        $condition = array('enabled' => 'on', 'description' => 'The description.');
        $world = new Testing_Feature_MockWorld(array());
        $config = new Feature_Config('foo', $condition, $world);
        $this->assertEquals($config->description(), 'The description.');
    }

    public function testIsEnabledForAcceptsRESTUser()
    {
        //we don't want to test the implementation of user bucketing here, just the public API
        $userId = 1;
        $user = $this->getMock('RESTUser');
        $user->expects($this->once())
            ->method('getUserId')
            ->will($this->returnValue($userId));
        $config = new Feature_Config('foo', array('enabled' => 'off'), new Testing_Feature_MockWorld(array()));
        $this->assertFalse($config->isEnabledFor($user));
    }

    public function testIsEnabledForAcceptsEtsyModelUser()
    {
        //we don't want to test the implementation of user bucketing here, just the public API
        $user = new EtsyModelUser();
        $user->userId = 1;
        $config = new Feature_Config('foo', array('enabled' => 'off'), new Testing_Feature_MockWorld(array()));
        $this->assertFalse($config->isEnabledFor($user));
    }


    ////////////////////////////////////////////////////////////////////////
    // Test helper methods.

    /*
     * Given a config stanza and a world configuration, we expect that
     * isEnabled() will return true and that variant will be a given
     * value (default 'on').
     */
    private function expectEnabled($stanza, $world, $variant = 'on')
    {
        $config = new Feature_Config('foo', $stanza, new Testing_Feature_MockWorld($world));
        $this->assertTrue($config->isEnabled());
        $this->assertEquals($config->variant(), $variant);

        if (is_array($stanza) && array_key_exists('enabled', $stanza) && $stanza['enabled'] === 0) {
            unset($stanza['enabled']);
            $this->expectEnabled($stanza, $world, $variant);
        }
    }

    /*
     * Given a config stanza and a world configuration, we expect that
     * isEnabled() will return false.
     */
    private function expectDisabled($stanza, $world)
    {
        $config = new Feature_Config('foo', $stanza, new Testing_Feature_MockWorld($world));
        $this->assertFalse($config->isEnabled());
        if (is_array($stanza) && array_key_exists('enabled', $stanza) && $stanza['enabled'] === 0) {
            unset($stanza['enabled']);
            $this->expectDisabled($stanza, $world);
        }
    }
}
