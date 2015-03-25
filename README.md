## TOC

1. Group Management
1. Running the Tests
1. Basic Example
  1. Include this Library Using Composer
  1. Set Up Your Authentication Information
  1. Prepare your data in $currentState and $newState
1. Expanded Example
  1. Create a ServiceAccountAuthenticator
  1. Ask the service account authenticator to authenticate with the appropriate scopes
  1. Create a Standard Policy object
  1. Create a Google Client and authenticate with Google
  1. Create a Google Apps Groups Controller and Update It
  1. Debugging, logging or prompting

## Group Management

Callers may use this class to update their Google Groups memberships
in bulk.  

- Membership data is provided via a PHP associative array that
defines the group names and member email addresses.
- It is expected that the caller will also provide a similar array, containing a cached representation of the group membership data from the last time an update
was made.  
- The update function in this class will then call the Google API to make any necessary additions or deletions from the group membership lists.

The advantage of using this wrapper layer rather than calling the Google
API directly is that this way makes it easier to manage aliases for groups
of groups, and other inter-group relationships, while minimizing the number
of remote API calls to Google that need to be made.

For example, imagine what you would have to do to update your group list
if a user's email address changed.  You'd have to find all of the groups
the user is a member of, and manually remove the old address and add the
new one.  Using this API, you can just rebuild all of the data for all
of the groups, call the 'update' function, and the GroupManager will make
the minimal number of required updates for you.

In the long run, this should lead to simpler, more maintainable code in
your application.

## Running the Tests

This library contains a test suite that uses PHPUnit and Prophecy to
insure that the classes provided here are correct.  The tests exercise
the Google Apps APIs, but do not make any calls to Google, so it is
not necessary to set up any authentication credentials just to run the tests.

1. Clone this repository
1. Run `composer install`
1. Run `./vendor/bin/phpunit tests`

All of the tests are also run by [Travis CI](https://travis-ci.org/westkingdom/google-api-extensions) on every commit.

## Basic Example

If you follow the instructions in the following sections, code similar to
the basic overview shown below should work.
```
use Westkingdom\GoogleAPIExtensions\GroupsManager;

$groupsManager = GroupsManager::createForDomain('My application', 'mydomain.org', $currentState);
$groupsManager->update($newState);
```
Even if you use this simple form, you need to understand how this API
searches for and uses your authentication data, and how to manage the state
of your data.  See below for more details.

### Include this Library Using Composer

The best way to install this library in your application is to use
Composer.  Simply add the following line to your composer.json file's
`require` section:
```
{
  "require": {
    "westkingdom/google-api-extensions": "~1"
  }
}
```
To use Composer with popular content management systems, please see
the following resources:

- Drupal: [Composer Generate](https://www.drupal.org/project/composer_generate)
- Joomla: [Getting Started with Composer and Joomla!](http://magazine.joomla.org/issues/issue-aug-2013/item/1450-getting-started-with-composer-and-joomla)
- Wordpress: [Using Composer with WordPress](https://roots.io/using-composer-with-wordpress/)

Of course, it is possible to use this library without composer; you just
need to be responsible for setting up the autoloader, or including the
class files yourself.  However, using Composer is strongly recommended.

### Set Up Your Authentication Information

Follow the [authorization information setup instructions](http://docs.westkingdom.org/en/latest/google-api/) on the 
[documentation website](http://docs.westkingdom.org).

### Prepare Your Data

This library expects you to accumulate all of the information about all
of your groups, and their memberships in a nested heirarchical array.

The structure is shown below in yaml, but you may store it in whatever
format is most convenient for your application.
```
GROUPNAME:
  lists:
    OFFICENAME:
      members:
        - user1@domain1.org
        - user2@domain2.org
      properties:
        group-name: 'Full name of Office'
```
Just repeat this structure for as many groups as you have.  If your groups
are heirarchical in nature, the relationships between the groups is NOT
represented in your groups state data.  You'll have to keep track of that
elsewhere.

Note also that it is the responsibility of the caller to keep track of
the current state and the new state.  The group manager will send updates
for just the changes that occure in the new state compared to the old state.
If you do not provide the current state, then groups will never be deleted,
and group members will never be removed.

Future: The group manager could provide an "export" function to build the
current state of the groups by calling the Google API.

## Expanded Example

If you would like more control over what happens in an update, you can
construct the internal classes yourself and modify them before making your
GroupsManager.

```
use Westkingdom\GoogleAPIExtensions\ServiceAccountAuthenticator;
use Westkingdom\GoogleAPIExtensions\StandardGroupPolicy;
use Westkingdom\GoogleAPIExtensions\GoogleAppsGroupsController;
use Westkingdom\GoogleAPIExtensions\GroupsManager;

$authenticator = ServiceAccountAuthenticator("My application");
$client = $authenticator->authenticate();
$policy = new StandardGroupPolicy('mydomain.org');
$controller = new GoogleAppsGroupsController($client, $policy);
$groupManager = new GroupsManager($controller, $currentState);
$groupManager->update($newState);
```

Note: If you also want to control the behavior of the batch operations,
you can provide a batch object to the GoogleAppsGroupsController constructor.
See below for details.

### Create a Service Authenticator

A service authenticator will help your application load its authentication
credential information from well-known files, so they do not need to be
hard-coded in the application source code.

`$authenticator = ServiceAccountAuthenticator("My application", $searchpath);`

"My application" is the name of your application; this will be passed
to any Google_Client created by the authenticator.

`$searchpath` is an array of paths to search for authentication files.
Relative paths are resolved relative to the current user's home directory.
The default searchpath is:

- .google-api
- /etc/google-api

### Authenticate

`$client = $authenticator->authenticate($serviceAccount, $scopes, $serviceToken);`

### Create a Standard Group Policy

`$policy = new StandardGroupPolicy('mydomain.org', $defaults);`

'mydomain.org' is the base domain for your Google Apps account.
`$defaults` contain default values for properties used by the policy.

### Create a Google Apps Group Controller

The Google Apps Group Controller is the object that actually talks
to the Google API.  Batch mode is always used; you can manage the
batch object yourself, as shown below:

```
$batch = new \Google_Http_Batch($client);
$controller = new GoogleAppsGroupsController($client, $policy, $batch);
$groupManager = new GroupsManager($controller, $currentState);
...
// When finished:
$groupManager->execute();
```

If you do not want to manage the batch object, just leave off those
lines, and the contorller will create and execute batches as needed.

### Create a Groups Object and Update It

The Groups object is responsible for evaluating how the new state
differs from the current state.  It then instructs the controller to make
whatever changes are necessary to update the current state to match
the new state.

```
$groupManager = new Groups($controller, $currentState);
$groupManager->update($newState);
```
Changes are always made in batch mode.  Batch mode can be handled for
you, or you can control it yourself, as shown in the previous section.

### Debugging, Logging or Prompting

If you'd like to know what the GroupManager is going to do before it
does it, you can use a BatchWrapper object.

```
use Westkingdom\GoogleAPIExtensions\BatchWrapper;

$client->setUseBatch(true);
$batch = new \Google_Http_Batch($client);
$batchWrapper = new BatchWrapper($batch);
$controller = new GoogleAppsGroupsController($client, $policy, $batch);
...
// To log or prompt or whatever:
$operationList = $batchWrapper->getSimplifiedRequests();

// When finished:
$batchWrapper->execute();
```

If you are only reporting / debugging, it is not necessary to create the
Google_Http_Batch at all; you can just use the BatchWrapper by itself.
