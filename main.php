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

// If we have a service account, that will give us even more access.
if ($service_account_info) {
  $client_id = $service_account_info['client-id'];
  $service_account_name = $service_account_info['email-address'];
  $key_file_location = $service_account_info['key-file'];
  $key_file_password = $service_account_info['key-file-password'];

  if (isset($_SESSION['service_token'])) {
    $client->setAccessToken($_SESSION['service_token']);
  }
  $key = file_get_contents($key_file_location);

  // https://www.googleapis.com/auth/books, https://www.googleapis.com/auth/admin.directory.group, https://www.googleapis.com/auth/admin.directory.group.readonly, https://www.googleapis.com/auth/admin.directory.group.member, https://www.googleapis.com/auth/admin.directory.group.member.readonly, https://www.googleapis.com/auth/apps.groups.settings, https://www.googleapis.com/auth/admin.directory.notifications, https://www.googleapis.com/auth/admin.directory.orgunit, https://www.googleapis.com/auth/admin.directory.orgunit.readonly, https://www.googleapis.com/auth/admin.directory.user, https://www.googleapis.com/auth/admin.directory.user.alias, https://www.googleapis.com/auth/admin.directory.user.alias.readonly, https://www.googleapis.com/auth/admin.directory.user.readonly, https://www.googleapis.com/auth/admin.directory.user.security, https://www.googleapis.com/auth/admin.directory.userschema, https://www.googleapis.com/auth/admin.directory.userschema.readonly, https://www.googleapis.com/auth/calendar, https://www.googleapis.com/auth/calendar.readonly
  $cred = new Google_Auth_AssertionCredentials(
    $service_account_name,
    array(
      // Books is only for testing.  The rest I think we actually need.
      Google_Service_Books::BOOKS,

      Google_Service_Groupssettings::APPS_GROUPS_SETTINGS,

      Google_Service_Directory::ADMIN_DIRECTORY_GROUP,
      Google_Service_Directory::ADMIN_DIRECTORY_GROUP_READONLY,

      Google_Service_Directory::ADMIN_DIRECTORY_GROUP_MEMBER,
      Google_Service_Directory::ADMIN_DIRECTORY_GROUP_MEMBER_READONLY,

      Google_Service_Directory::ADMIN_DIRECTORY_NOTIFICATIONS,

      Google_Service_Directory::ADMIN_DIRECTORY_ORGUNIT,
      Google_Service_Directory::ADMIN_DIRECTORY_ORGUNIT_READONLY,

      Google_Service_Directory::ADMIN_DIRECTORY_USER,
      Google_Service_Directory::ADMIN_DIRECTORY_USER_READONLY,

      Google_Service_Directory::ADMIN_DIRECTORY_USER_ALIAS,
      Google_Service_Directory::ADMIN_DIRECTORY_USER_ALIAS_READONLY,

      Google_Service_Directory::ADMIN_DIRECTORY_USER_SECURITY,

      Google_Service_Directory::ADMIN_DIRECTORY_USERSCHEMA,
      Google_Service_Directory::ADMIN_DIRECTORY_USERSCHEMA_READONLY,

      Google_Service_Calendar::CALENDAR,
      Google_Service_Calendar::CALENDAR_READONLY,

    ),
    $key,
    $key_file_password
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

// Test the actual Google API

$domain = "westkingdom.org";
$group_email = "west-webminister@$domain";

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
$data = $service->groups->get($group_email);
var_export($data);
print("\n");

// TEMPORARY
$service->users->delete("first.last@$domain");

$user = new Google_Service_Directory_User(array(
    'name' => array(
      'familyName' => 'Firstname',
      'givenName' => 'Lastname',
    ),
    'primaryEmail' => "first.last@$domain",
    'password' => sha1('secretsecret'),
    'hashfunction' => 'SHA-1',
  ));

$service->users->insert($user);



$data = $service->users->get("first.last@$domain");
var_export($data);
print("\n");


/*
  public $addresses;
  public $agreedToTerms;
  public $aliases;
  public $changePasswordAtNextLogin;
  public $creationTime;
  public $customSchemas;
  public $customerId;
  public $deletionTime;
  public $emails;
  public $etag;
  public $externalIds;
  public $hashFunction;
  public $id;
  public $ims;
  public $includeInGlobalAddressList;
  public $ipWhitelisted;
  public $isAdmin;
  public $isDelegatedAdmin;
  public $isMailboxSetup;
  public $kind;
  public $lastLoginTime;
  protected $nameType = 'Google_Service_Directory_UserName';
  protected $nameDataType = '';
  public $nonEditableAliases;
  public $orgUnitPath;
  public $organizations;
  public $password;
  public $phones;
  public $primaryEmail;
  public $relations;
  public $suspended;
  public $suspensionReason;
  public $thumbnailPhotoUrl;
*/


$member = new Google_Service_Directory_Member(array(
                        'email' =>"first.last@$domain",
                        'role' => 'MEMBER',
                        'type' => 'USER'));
$service->members->insert($group_email, $member);


// Get members of a group
$data = $service->members->listMembers($group_email);
var_export($data);
print("\n");

$service->members->delete($group_email, "first.last@$domain");


$newgroup = new Google_Service_Directory_Group(array(
        'email' => "test-group@$domain",
        'name' => 'This is a test group',
  ));

$service->groups->insert($newgroup);


$data = $service->groups->delete("test-group@$domain");


$newalias = new Google_Service_Directory_Alias(array(
  'alias' => "uber-seneschal@$domain",
  ));
$service->groups_aliases->insert("west-seneschal@$domain", $newalias);


// n.b. inserting an alias also adds a non-editable alias, but deleting
// an alias does not delete its non-editable counterpart.
$service->groups_aliases->delete("west-seneschal@$domain", "uber-seneschal@$domain");

// List all the groups
$opt = array('domain' => "$domain");
$data = $service->groups->listGroups($opt);
var_export($data);
print("\n");

$service->users->delete("first.last@$domain");

$service = new Google_Service_Groupssettings($client);

$settingData = new Google_Service_Groupssettings_Groups();

// Some API calls require that we request that the returned data be
// sent as JSON.  The PHP API for the Google Apps API only works with
// JSON, but some calls default to returning XML.
$opt_params = array(
  'alt' => "json"
);
$data = $service->groups->get($group_email, $opt_params);

var_export($data);
print("\n");

// INVITED_CAN_JOIN or CAN_REQUEST_TO_JOIN, etc.
$settingData->setWhoCanJoin("CAN_REQUEST_TO_JOIN");
// ALL_MANAGERS_CAN_POST, ALL_IN_DOMAIN_CAN_POST, works
// ANYONE_CAN_POST returns 'permission denied'.
$settingData->setWhoCanPostMessage("ANYONE_CAN_POST");

$data = $service->groups->patch($group_email, $settingData);

