<?php
namespace OregonCatholicPress\Feature;

/**
 * The interface Feature_Config needs to the outside world. This class
 * is used in the normal case but tests can use a mock
 * version. There's a reasonable argument that the code in Logger
 * should just be moved into this class since there's a fair bit of
 * passing stuff back and forth between here and Logger and Logger has
 * no useful independent existence.
 */
class World
{

    private $logger;
    private $selections = array();
    private $get = [];

    public function __construct($logger, $params = [])
    {
        $this->logger = $logger;
        $this->get = (isset($params['get'])) ? $params['get'] : $_GET;
    }

    /*
     * Get the config value for the given key.
     */
    public function configValue($name, $default = null)
    {
        $name;
        return $default; // IMPLEMENT FOR YOUR CONTEXT
    }

    /**
     * UAId of the current request.
     */
    public function uaid()
    {
        return null; // IMPLEMENT FOR YOUR CONTEXT
    }

    /**
     * User Id of the currently logged in user or null.
     */
    public function userId()
    {
        return null; // IMPLEMENT FOR YOUR CONTEXT
    }

    /**
     * Login name of the currently logged in user or null. Needs the
     * ORM. If we're running as part of an Atlas request we ignore the
     * passed in userId and return instead the Atlas user name.
     */
    public function userName($userId)
    {
        $userId;
        return null; // IMPLEMENT FOR YOUR CONTEXT
    }

    /**
     * Is the given user a member of the given group? (This currently,
     * like the old config system, uses numeric group Ids in the
     * config file, in order to speed up the lookup--the numeric Id is
     * the primary key and we save having to look up the group by
     * name.)
     */
    public function inGroup($userId, $groupId)
    {
        $userId;
        $groupId;
        return null; // IMPLEMENT FOR YOUR CONTEXT
    }

    /**
     * Is the current user an admin?
     *
     * @param $userId the id of the relevant user, either the
     * currently logged in user or some other user.
     */
    public function isAdmin($userId)
    {
        $userId;
        return false; // IMPLEMENT FOR YOUR CONTEXT
    }

    /**
     * Is this an internal request?
     */
    public function isInternalRequest()
    {
        return false; // IMPLEMENT FOR YOUR CONTEXT
    }

    /*
     * 'features' query param for url overrides.
     */
    public function urlFeatures()
    {
        return array_key_exists('features', $this->get) ? $this->get['features'] : '';
    }

    /*
     * Produce a random number in [0, 1) for RANDOM bucketing.
     */
    public function random()
    {
        return mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax();
    }

    /*
     * Produce a randomish number in [0, 1) based on the given id.
     */
    public function hash($int)
    {
        return self::mapHex(hash('sha256', $int));
    }

    /*
     * Record that $variant has been selected for feature named $name
     * by $selector and pass the same information along to the logger.
     */
    public function log($name, $variant, $selector)
    {
        $this->selections[] = array($name, $variant, $selector);
        $this->logger->log($name, $variant, $selector);
    }

    /*
     * Get the list of selections that we have recorded. The public
     * API for getting at the selections is Feature::selections which
     * should be the only caller of this method.
     */
    public function selections()
    {
        return $this->selections;
    }

    /**
     * Map a hex value to the half-open interval [0, 1) while
     * preserving uniformity of the input distribution.
     *
     * @param string $hex a hex string
     * @return float
     */
    private static function mapHex($hex)
    {
        $len = min(40, strlen($hex));
        $maxValue = 1 << $len;
        $value = 0;
        for ($idx = 0; $idx < $len; $idx++) {
            $bit = hexdec($hex[$idx]) < 8 ? 0 : 1;
            $value = ($value << 1) + $bit;
        }
        $float = $value / $maxValue;
        return $float;
    }
}
