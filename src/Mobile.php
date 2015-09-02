<?php
namespace OregonCatholicPress\Feature\World;

use Feature\World as World;

/**
 * This sublcass of Feature_World overrides UAID and UserID so that
 * feature rampups can maintain consistency on mobile devices.
*/

class Mobile extends World
{
    private $udid;
    private $userID;

    private $name;
    private $variant;
    private $selector;

    public function __construct($udid, $userID, $logger)
    {
        parent::__construct($logger);
        $this->udid = $udid;
        $this->userID = $userID;
    }

    public function uaid()
    {
        return $this->udid;
    }

    public function userID()
    {
        return $this->userID;
    }

    public function log($name, $variant, $selector)
    {
        parent::log($name, $variant, $selector);

        $this->name = $name;
        $this->variant = $variant;
        $this->selector = $selector;
    }

    public function getLastName()
    {
        return $this->name;
    }

    public function getLastVariant()
    {
        return $this->variant;
    }

    public function getLastSelector()
    {
        return $this->selector;
    }

    public function clearLastFeature()
    {
        $this->selector = null;
        $this->name = null;
        $this->variant = null;
    }
}
