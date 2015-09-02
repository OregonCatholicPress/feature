<?php
namespace Feature;

/**
 * @group dbunit
 * @medium
 */
class WorldTest extends PHPUnit_Extensions_MultipleDatabase_TestCase
{
    private $uaid;
    private $world;
    private $userId;
    private $server;
    private $globals;

    public function setUp($params = [])
    {
        parent::setUp();
        UAIDCookie::resetState();
        UAIDCookie::setUpUAID();
        $this->uaid = UAIDCookie::getSecureCookie();
        $this->assertNotNull($this->uaid);

        $logger = $this->getMock('Logger', array('log'));
        $this->world = new Feature_World($logger);
        $this->userId = 991;

        $this->setLoggedUserId(null);
        $this->assertNull(Std::loggedUser());
        $this->server = $params['server'] ? $params['server'] : [];
        $this->globals = $params['global'] ? $params['global'] : [];
    }

    public function testIsAdminWithBlankUAIDCookie()
    {
        $this->setLoggedUserId($this->userId);

        $this->assertFalse($this->world->isAdmin($this->userId));
    }

    public function testIsAdminWithValidNonAdminUserUAIDCookie()
    {
        $this->setLoggedUserId($this->userId);
        $this->uaid->set(UAIDCookie::USER_ID_ATTRIBUTE, $this->userId);

        $this->assertFalse($this->world->isAdmin($this->userId));
    }

    public function testIsAdminWithValidAdminUAIDCookie()
    {
        $this->setLoggedUserId($this->userId);
        $this->uaid->set(UAIDCookie::USER_ID_ATTRIBUTE, $this->userId);
        $this->uaid->set(UAIDCookie::ADMIN_ATTRIBUTE, '1');

        $this->assertTrue($this->world->isAdmin($this->userId));
    }

    public function testIsAdminWithNonLoggedInAdminAndValidAdminUAIDCookie()
    {
        $this->setLoggedUserId(null);
        $this->uaid->set(UAIDCookie::USER_ID_ATTRIBUTE, $this->userId);
        $this->uaid->set(UAIDCookie::ADMIN_ATTRIBUTE, '1');

        $this->assertFalse($this->world->isAdmin($this->userId));
    }

    public function testIsAdminWithLoggedInAdminUserAndBlankUAIDCookie()
    {
        $user = $this->adminUser();
        $this->setLoggedUserId($user->userId);

        $this->assertTrue($this->world->isAdmin($user->userId));
    }

    public function testIsAdminWithLoggedInNonAdminUserAndBlankUAIDCookie()
    {
        $user = $this->nonAdminUser();
        $this->setLoggedUserId($user->userId);

        $this->assertFalse($this->world->isAdmin($user->userId));
    }

    public function testIsAdminWithNonLoggedInAdminUserAndBlankUAIDCookie()
    {
        $user = $this->adminUser();
        $this->setLoggedUserId(null);

        $this->assertTrue($this->world->isAdmin($user->userId));
    }

    public function testIsAdminWithNonLoggedInNonAdminUserAndBlankUAIDCookie()
    {
        $user = $this->nonAdminUser();
        $this->setLoggedUserId(null);

        $this->assertFalse($this->world->isAdmin($user->userId));
    }

    public function testAtlasWorld()
    {
        $user = $this->atlasUser();
        $this->setLoggedUserId($user->id);
        $this->setAtlasRequest(true);

        $this->assertFalse($this->world->isAdmin($user->id));
        $this->assertFalse($this->world->inGroup($user->id, 1));
        $this->assertEquals($user->id, $this->world->userID());

        $this->setAtlasRequest(false);
    }

    public function testHash()
    {
        $this->assertInternalType('float', $this->world->hash('somevalue'));

        $this->assertEquals(
            $this->world->hash('somevalue'),
            $this->world->hash('somevalue'),
            'ensure return value is consistent'
        );

        $this->assertGreaterThanOrEqual(0, $this->world->hash('somevalue'));
        $this->assertLessThan(1, $this->world->hash('somevalue'));
    }

    protected function getDatabaseConfigs()
    {
        $indexYml = dirname(__FILE__) . '/data/world/etsyIndex.yml';
        if (!file_exists($indexYml)) {
            throw new Exception($indexYml . ' does not exist');
        }
        $builder = new PHPUnit_Extensions_MultipleDatabase_DatabaseConfig_Builder();
        $etsyIndex = $builder
            ->connection(Testing_EtsyORM_Connections::ETSY_INDEX())
            ->dataSet(new PHPUnit_Extensions_Database_DataSet_YamlDataSet($indexYml))
            ->build();

        $auxYml = dirname(__FILE__) . '/data/world/etsyAux.yml';
        if (!file_exists($auxYml)) {
            throw new Exception($auxYml . ' does not exist');
        }
        $builder = new PHPUnit_Extensions_MultipleDatabase_DatabaseConfig_Builder();
        $etsyAux = $builder
            ->connection(Testing_EtsyORM_Connections::ETSY_AUX())
            ->dataSet(new PHPUnit_Extensions_Database_DataSet_YamlDataSet($auxYml))
            ->build();

        return array($etsyIndex, $etsyAux);
    }

    private function nonAdminUser()
    {
        return EtsyORM::getFinder('User')->find(1);
    }

    private function adminUser()
    {
        return EtsyORM::getFinder('User')->find(2);
    }

    private function atlasUser()
    {
        return EtsyORM::getFinder('Staff')->find(3);
    }

    private function setAtlasRequest($isAtlas)
    {
        $this->server["atlas_request"] = $isAtlas ? 1 : 0;
    }

    private function setLoggedUserId($userId)
    {
        //Std::loggedUser() uses this global
        $this->globals['cookie_userId'] = $userId;
    }
}
