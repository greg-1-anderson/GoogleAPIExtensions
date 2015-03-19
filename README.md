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

## Running the Tests

This library contains a test suite that uses PHPUnit and Prophecy to
insure that the classes provided here are correct.  The tests exercise
the Google Apps APIs, but do not make any calls to Google, so it is
not necessary to set up any authentication credentials just to run the tests.

1. Clone this repository
1. Run `composer install`
1. Run `./vendor/bin/phpunit tests`

All of the tests are also run on [Travis CI](https://travis-ci.org/westkingdom/google-api-extensions) on every commit.

## Using the Code

### Overview

1. Include this Library Using Composer
1. Set Up Your Authentication Information
1. Prepare your data in $currentState and $newState
1. Create a ServiceAccountAuthenticator
1. Ask the service account authenticator to authenticate with the appropriate scopes
1. Create a Standard Policy object
1. Create a Google Client and authenticate with Google
1. Create a Google Apps Groups Controller and Update It

### Basic Example

If you follow the instructions in the following sections, code similar to
the basic overview shown below should work.
```
use Westkingdom\GoogleAPIExtensions\ServiceAccountAuthenticator;
use Westkingdom\GoogleAPIExtensions\StandardGroupPolicy;
use Westkingdom\GoogleAPIExtensions\GoogleAppsGroupsController;
use Westkingdom\GoogleAPIExtensions\Groups;

$authenticator = ServiceAccountAuthenticator("My application");
$client = $authenticator->authenticate();
$policy = new StandardGroupPolicy('mydomain.org');
$controller = new GoogleAppsGroupsController($client, $policy);
$groupManager = new Groups($controller, $currentState);
$groupManager->update($newState);
```

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

Follow the [authorization information setup instructions](http://docs.westkingdom.org/en/latest/google-api/) in the 
documentation.

### Prepare Your Data

This library expects you to accumulate all of the information about all
of your groups, and their memberships in a nested heirarchical array.

The structure is shown below in yaml, but you may store it in whatever
format is most convenient for your application.

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
$client->setUseBatch(true);
$batch = new \Google_Http_Batch($client);
$controller = new GoogleAppsGroupsController($client, $policy, $batch);
...
// When finished:
$batch->execute();
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

