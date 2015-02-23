<?php

include dirname(__FILE__) . "/vendor/autoload.php";

use Symfony\Component\Yaml\Yaml;
use GoogleAPIExtensions\Groups;

$groupData = file_get_contents(dirname(__FILE__) . "/sample_data/westkingdom.org.yaml");
$parsed = Yaml::parse($groupData);
$existingState = $parsed['smartlist::subdomain_lists'];

$groupManager = new Groups($existingState);

$newState = $existingState;
$newState['west']['lists']['webminister']['members'][] = 'new.admin@somewhere.com';

$auth = array();

$groupManager->update($auth, $newState);
