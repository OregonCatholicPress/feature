<?php
namespace OregonCatholicPress\Feature\Tests;

use OregonCatholicPress\Feature\Logger as Logger;

class FakeLogger extends Logger
{
    public function log($name, $variant, $selector)
    {
        $name;
        $variant;
        $selector;
    }
}
