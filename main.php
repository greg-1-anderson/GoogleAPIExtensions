<?php

include dirname(__FILE__) . "/vendor/autoload.php";

use Symfony\Component\Yaml\Yaml;
use GoogleAPIExtensions\Groups;

$groupData = file_get_contents(dirname(__FILE__) . "/sample_data/westkingdom.org.yaml");
$existingState = Yaml::parse($groupData);

$groupManager = new Groups($existingState);

$newState = $existingState;
$newState['groups']['west']['lists']['webminister']['members'][] = 'new.admin@somewhere.com';

$auth = array();

$groupManager->update($auth, $newState);
