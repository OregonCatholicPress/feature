<?php
namespace OregonCatholicPress\Feature\Tests;

use OregonCatholicPress\Feature\World as World;

class FakeWorld extends World
{
    const DEBUG = false;
    const INDENT = 4;
    const UNINDENT = -4;

    private $params = [];

    public function __construct(array $params = [])
    {
        $this->logger = new FakeLogger();
        $this->params = $params;
    }

    public function inGroup($userId, $groupId)
    {
        if (isset($this->params['inGroup'])
         && isset($this->params['inGroup'][$userId])) {
            return ($this->params['inGroup'][$userId] === $groupId);
        }
        return false;
    }

    public function isAdmin($userId)
    {
        if (self::DEBUG) {
            $this->dbg('FakeWorld::isAdmin()', self::INDENT);
        }
        if (isset($this->params['isAdmin'])) {
            if (self::DEBUG) {
                $this->dbg('- $this->params["isAdmin"]: '.$this->params['isAdmin']);
                $this->dbg(null, self::UNINDENT);
            }
            return $this->params['isAdmin'];
        }
        if (self::DEBUG) {
            $this->dbg('- false');
            $this->dbg(null, self::UNINDENT);
        }
        return false;
    }

    public function uaid()
    {
        if (isset($this->params['uaid'])) {
            return $this->params['uaid'];
        }
        return null;
    }

    public function userId()
    {
        if (isset($this->params['userID'])) {
            return $this->params['userID'];
        }
        return null;
    }

    public function userName($userId)
    {
        return isset($this->params['userName'])
          ? $this->params['userName']
          : '';
    }

    public function isInternalRequest()
    {
        return isset($this->params['isInternal'])
          ? $this->params['isInternal']
          : false;
    }

    public function urlFeatures()
    {
        return isset($this->params['urlFeatures'])
          ? $this->params['urlFeatures']
          : '';
    }

    public function dbg($msg, $indent = 0)
    {
        static $first = false; // should be true if we leave this method in
        static $pad = self::UNINDENT; // should be zero (0) if we leave this in
        if ($first) {
            echo "\n";
            $first = false;
        }
        if ($indent) {
            $pad += $indent;
        }
        if ($msg) {
            echo str_repeat(' ', $pad).$msg."\n";
        }
    }
}
