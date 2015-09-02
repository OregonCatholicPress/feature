<?php
namespace OregonCatholicPress\Tests;

use \OregonCatholicPress\Feature\Feature;
use PHPUnit_Framework_TestCase as Test;

class DefaultConfigFeatureTest extends Test
{
    private function fauxUser()
    {
        return (object) ['user_id' => 12];
    }
    
    public function testGetInstance()
    {
        $this->assertEquals(get_class(Feature::getInstance()), 'OregonCatholicPress\\Feature\\Instance');
    }

    public function testIsEnabled()
    {
        $this->assertFalse(Feature::isEnabled('some-feature-flag'));
    }

    public function testIsEnabledFor()
    {
        $this->assertFalse(Feature::isEnabledFor('some-feature-flag', $this->fauxUser()));
    }
    
    public function testIsEnabledBucketingBy()
    {
        $this->assertFalse(Feature::isEnabledBucketingBy('some-feature-flag', 'made-up-campaign'));
    }

    public function testVariant()
    {
        $this->assertEquals('off', Feature::variant('some-feature-flag'));
    }

    public function testVariantFor()
    {
        $this->assertEquals('off', Feature::variantFor('some-feature-flag', $this->fauxUser()));
    }

    public function testDescription()
    {
        $this->assertEquals('No description.', Feature::description('some-feature-flag'));
    }

    public function testData()
    {
        $this->assertEquals([], Feature::data('some-feature-flag'));
    }

    public function testVariantData()
    {
        $this->assertEquals([], Feature::variantData('some-feature-flag'));
    }

    public function testSelections()
    {
        $this->assertEquals([], Feature::selections());
    }
}
