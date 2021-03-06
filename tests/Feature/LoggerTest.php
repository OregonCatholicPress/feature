<?php
namespace Feature;

// require_once "Loader.php";

class LoggerTest extends PHPUnit_Framework_TestCase
{

    // Test cases borrowed from AB2_Logger_GALoggerTest

    public function testNoLogging()
    {
        $this->assertEquals('', Feature_Logger::getGAJavascript(array()));
    }

    public function testLogOne()
    {
        $selections = array();
        $selections[] = array('TEST_key1', 'TEST_var1', 123);
        $javascript = Feature_Logger::getGAJavascript($selections);
        $this->assertEquals("Etsy.GA.track(['_setCustomVar', 2, 'AB', 'TEST_key1.TEST_var1', 3]);", $javascript);
    }

    public function testLogTwo()
    {
        $selections = array();
        $selections[] = array('TEST_key1', 'TEST_var1', 123);
        $selections[] = array('foo', 'bar', 123);
        $javascript = Feature_Logger::getGAJavascript($selections);
        $this->assertEquals(
            "Etsy.GA.track(['_setCustomVar', 2, 'AB', 'TEST_key1.TEST_var1..foo.bar', 3]);",
            $javascript
        );
    }

    public function testTooLong()
    {
        $selections = array();
        $pairs = array();
        foreach (array('a', 'b', 'c', 'd', 'e') as $x) {
            $selections[] = array($x, 'xxxxxxxxxx', 123);
            $pairs[] = "$x.xxxxxxxxxx";
        }
        // This one should not be included in the Javascript because
        // we already have 12*5=60 chars and this pair would add three
        // more pushing us over the limit of 62.
        $selections[] = array('f', 'x', 123);
        $value = implode('..', $pairs);
        $javascript = Feature_Logger::getGAJavascript($selections);
        $this->assertEquals("Etsy.GA.track(['_setCustomVar', 2, 'AB', '$value', 3]);", $javascript);
    }
}
