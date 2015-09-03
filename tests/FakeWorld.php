<?php
namespace OregonCatholicPress\Feature\Tests;

use OregonCatholicPress\Feature\World as World;

class FakeWorld extends World 
{
    private $params = [];

    public function __construct(array $params = [])
    {
        $this->logger = new FakeLogger();
        $this->params = $params;
    }

    public function uaid()
    {
        if (isset($this->params['uaid'])) {
            return $this->params['uaid'];
        }
        return null;
    }
}
