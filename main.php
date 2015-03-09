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
if ($api_key) {
  $client->setDeveloperKey($api_key);
}

print("Set authentication information.\n");

// If we have a service account, that will give us even more access.
if ($service_account_info) {
  var_export($service_account_info);
  print("\n");

  $client_id = $service_account_info['client-id'];
  $service_account_name = $service_account_info['email-address'];
  $key_file_location = $service_account_info['key-file'];

  if (isset($_SESSION['service_token'])) {
    $client->setAccessToken($_SESSION['service_token']);
  }
  $key = file_get_contents($key_file_location);

  // https://www.googleapis.com/auth/admin.directory.group, https://www.googleapis.com/auth/admin.directory.group.readonly, https://www.googleapis.com/auth/admin.directory.group.member, https://www.googleapis.com/auth/admin.directory.group.member.readonly, https://www.googleapis.com/auth/apps.groups.settings, https://www.googleapis.com/auth/books
  $cred = new Google_Auth_AssertionCredentials(
    $service_account_name,
    array(
      Google_Service_Groupssettings::APPS_GROUPS_SETTINGS,
      Google_Service_Directory::ADMIN_DIRECTORY_GROUP,
      Google_Service_Directory::ADMIN_DIRECTORY_GROUP_READONLY,

      Google_Service_Directory::ADMIN_DIRECTORY_GROUP_MEMBER,
      Google_Service_Directory::ADMIN_DIRECTORY_GROUP_MEMBER_READONLY,

      Google_Service_Books::BOOKS,
    ),
    $key,
    'notasecret'
  );
  //
  // Very important step:  the service account must also declare the
  // identity (via email address) of a user with admin priviledges that
  // it would like to masquerade as.  This is not well documented.
  //
  // See:  http://stackoverflow.com/questions/22772725/trouble-making-authenticated-calls-to-google-api-via-oauth
  //
  $cred->sub = $service_account_info['delegate-user-email'];
  $client->setAssertionCredentials($cred);
  if ($client->getAuth()->isAccessTokenExpired()) {
    $client->getAuth()->refreshTokenWithAssertion($cred);
  }
  $_SESSION['service_token'] = $client->getAccessToken();
}

print("Get books service.\n");

$service = new Google_Service_Books($client);

print("Call books service:\n");

$optParams = array('filter' => 'free-ebooks');
$results = $service->volumes->listVolumes('Henry David Thoreau', $optParams);
echo "### Results Of Call:\n";
foreach ($results as $item) {
  echo $item['volumeInfo']['title'], "\n";
}

print("Get directory service.\n");

$service = new Google_Service_Directory($client);



print("Call directory service:\n");

// Get info about a group
$data = $service->groups->get("west-webminister@westkingdom.org");
var_export($data);
print("\n");

// Get members of a group
$data = $service->members->listMembers("west-webminister@westkingdom.org");
var_export($data);
print("\n");

// List all the groups
$opt = array('domain' => 'westkingdom.org');
$data = $service->groups->listGroups($opt);
var_export($data);
print("\n");

// The group settings APIs are still failing with an error:
//   Domain cannot use Api, Groups service is not installed.
// See: https://groups.google.com/forum/#!msg/google-apps-manager/EUz1aYmrnX4/zVe2tvnkqVoJ
// Also, on this page: https://admin.google.com/westkingdom.org/AdminHome#GroupDetails:groupEmail=west-webminister%2540westkingdom.org&flyout=rolesPermissions
// It says: If Groups for Business is activated later: The selected access level setting will include additional features
$service = new Google_Service_Groupssettings($client);

$data = $service->groups->get("west-webminister@westkingdom.org");
//$data = $service->groups->get("02pta16n2up3ah0");

var_export($data);
print("\n");
exit(0);

$groupData = file_get_contents(dirname(__FILE__) . "/sample_data/westkingdom.org.yaml");
$parsed = Yaml::parse($groupData);
$existingState = $parsed['smartlist::subdomain_lists'];

$groupManager = new Groups($existingState);

$newState = $existingState;
$newState['west']['lists']['webminister']['members'][] = 'new.admin@somewhere.com';

$auth = array();

$groupManager->update($client, $newState);
