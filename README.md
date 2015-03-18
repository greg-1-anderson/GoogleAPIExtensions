Group Management
================
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

Running the Tests
=================
This library contains a test suite that uses PHPUnit and Prophecy to
insure that the classes provided here are correct.  The tests exercise
the Google Apps APIs, but do not make any calls to Google, so it is
not necessary to set up any authentication credentials just to run the tests.

1. Clone this repository
1. Run `composer install`
1. Run `./vendor/bin/phpunit tests`

All of the tests are also run on [Travis CI](https://travis-ci.org/westkingdom/google-api-extensions) on every commit.

Using the Code
==============

Overview:

1. TODO: publish this library in Packagist so that it can be easily loaded from composer.json.
1. [Set up your authorization information](http://docs.westkingdom.org/en/latest/google-api/)
1. Prepare your data in $currentState and $newState
1. Create a Standard Policy object
1. Create a Google Client and authenticate with Google
1. Create a Google Apps Groups Controller
1. Tell the Groups Controller to update your group

Example:
```
$client = new Google_Client();
$client->setApplicationName("My application");
// Authenticate $client
$policy = new StandardGroupPolicy('mydomain.org');
$controller = new GoogleAppsGroupsController($client, $policy);
$groupManager = new Westkingdom\GoogleAPIExtensions\Groups($controller, $currentState);
$groupManager->update($newState);
```
