<?php

$testsFolder = dirname(__FILE__);
$projectFolder = dirname($testsFolder);

require $projectFolder.join(DIRECTORY_SEPARATOR, ['', 'vendor', 'autoload.php']);
require $testsFolder.DIRECTORY_SEPARATOR.'FakeLogger.php';
require $testsFolder.DIRECTORY_SEPARATOR.'FakeWorld.php';
