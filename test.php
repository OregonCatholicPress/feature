<?php
require 'vendor/autoload.php';

use \OregonCatholicPress\Feature\Feature as Feature;

function varstr($var){
    if (is_object($var)){
        return get_class($var);
    } elseif (is_array($var)){
        return print_r($var, true);;
    }
    return $var;
}

function equals($name, $a, $b){
    $result = ($a === $b);
    if ($result){
        echo 'okay '.$name."\n";
	} elseif ( ! $result){
        echo '$a does not $b: ['.varstr($a).'] compared to: ['.varstr($b)."]\n"; 
    }
}

$faux_user = (object) ['user_id' => 12]; 
equals('Feature::getInstance()', get_class(Feature::getInstance()), 'OregonCatholicPress\Feature\Instance');
equals('Feature::isEnabled()', Feature::isEnabled('someflag'), false);
equals('Feature::isEnabledFor()', Feature::isEnabledFor('someflag', $faux_user), false);
equals('Feature::isEnabledBucketingBy()', Feature::isEnabledBucketingBy('someflag', 'made-up-campaign'), false);
equals('Feature::variant()', Feature::variant('first-ab'), 'off');
equals('Feature::variantFor()', Feature::variantFor('second-ab', $faux_user), 'off');
equals('Feature::description()', Feature::description('first-ab'), 'No description.');
equals('Feature::data()', Feature::data('first-ab'), []);
equals('Feature::variantData()', Feature::variantData('second-ab'), []);
equals('Feature::clearCacheForTests()', Feature::clearCacheForTests(), null);
equals('Feature::selections()', Feature::selections(), []);

