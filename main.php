<?php

include dirname(__FILE__) . "/vendor/autoload.php";

use Symfony\Component\Yaml\Yaml;
use GoogleAPIExtensions\Groups;

// Look up our API key, if we can find it.
$api_key = FALSE;
$home = $_SERVER['HOME'];
foreach (array("$home/.google-api/server.key", "/etc/google-api/server.key") as $key_file) {
  if (file_exists($key_file)) {
    $api_key = trim(file_get_contents($key_file));
  }
}

// Look up our service account, if we can find it.
$service_account_info = FALSE;
foreach (array("$home/.google-api/service-account.yaml", "/etc/google-api/service-account.yaml") as $service_account_info_file) {
  if (file_exists($service_account_info_file)) {
    $service_account_info = Yaml::parse($service_account_info_file);
    if ($service_account_info['key-file'][0] != '/') {
      $service_account_info['key-file'] = dirname($service_account_info_file) . '/' . basename($service_account_info['key-file']);
    }
  }
}

// Create a new google client.  We need this for all API access.
$client = new Google_Client();
$client->setApplicationName("Google Group Test");

// If we have an API key, that will give us a certain amount of access.
if (FALSE && $api_key) {
  $client->setDeveloperKey($api_key);
}

// If we have a service account, that will give us even more access.
if ($service_account_info) {
  $client_id = $service_account_info['client-id'];
  $service_account_name = $service_account_info['email-address'];
  $key_file_location = $service_account_info['key-file'];

  if (isset($_SESSION['service_token'])) {
    $client->setAccessToken($_SESSION['service_token']);
  }
  $key = file_get_contents($key_file_location);
  $cred = new Google_Auth_AssertionCredentials(
    $service_account_name,
    array(Google_Service_Groupssettings::APPS_GROUPS_SETTINGS),
    $key
  );
  $client->setAssertionCredentials($cred);
  if ($client->getAuth()->isAccessTokenExpired()) {
    $client->getAuth()->refreshTokenWithAssertion($cred);
  }
  $_SESSION['service_token'] = $client->getAccessToken();
}

// Let's ask for some information about a group!
$service = new Google_Service_Groupssettings($client);

$data = $service->groups->get("west-webminister@westkingdom.org");

var_export($data);
exit(0);

$groupData = file_get_contents(dirname(__FILE__) . "/sample_data/westkingdom.org.yaml");
$parsed = Yaml::parse($groupData);
$existingState = $parsed['smartlist::subdomain_lists'];

$groupManager = new Groups($existingState);

$newState = $existingState;
$newState['west']['lists']['webminister']['members'][] = 'new.admin@somewhere.com';

$auth = array();

$groupManager->update($client, $newState);
