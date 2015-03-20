<?php

use Westkingdom\GoogleAPIExtensions\GoogleAppsGroupsController;
use Westkingdom\GoogleAPIExtensions\StandardGroupPolicy;
use Westkingdom\GoogleAPIExtensions\GroupPolicy;
use Westkingdom\GoogleAPIExtensions\BatchStandin;

use Symfony\Component\Yaml\Dumper;

class GoogleAppsGroupsControllerTestCase extends PHPUnit_Framework_TestCase {

  protected $client;
  protected $policy;

  public function setUp() {
    parent::setup();

    // Create a new client, set it to use batch mode, but do not authenticate
    $this->client = new Google_Client();
    $this->client->setApplicationName("Google Apps Groups Controller Test");
    $this->client->setUseBatch(true);
    // Create a standard group policy for testdomain.com.
    $this->policy = new StandardGroupPolicy('testdomain.com');
  }

  function testGroupsController() {
    // Use a batch standin, which acts like a Google_Http_Batch, but
    // merely accumulates the requests added to it, and returns them
    // to us when requested.
    $batch = new BatchStandin();

    // Create a new Google Apps group controller, and add some users
    // and groups to it.
    $controller = new GoogleAppsGroupsController($this->client, $this->policy, $batch);

    $controller->insertOffice('north', 'president', array());
    $controller->insertMember('north', 'president', 'franklin@testdomain.com');
    $controller->removeMember('north', 'president', 'franklin@testdomain.com');
    $controller->insertMember('north', 'vice-president', 'Garner');
    $controller->removeMember('north', 'vice-president', 'Garner');
    $controller->deleteOffice('north', 'president', array());

    // The expected list of requests corresponding to the calls above:
    //
    // We removed an entry to match a code change to work around a bug
    // or permissions problem:
    //
    //-
    //  url: /groups/v1/groups/north-president%40testdomain.com
    //  body: '{"whoCanJoin":"INVITED_CAN_JOIN","whoCanPostMessage":"ALL_IN_DOMAIN_CAN_POST"}'
    $expected = <<< EOT
-
  url: /admin/directory/v1/groups
  body: '{"email":"north-president@testdomain.com","name":"North President"}'
-
  url: /admin/directory/v1/groups/north-president%40testdomain.com/members
  body: '{"email":"franklin@testdomain.com","role":"MEMBER","type":"USER"}'
-
  url: /admin/directory/v1/groups/north-president%40testdomain.com/members/franklin%40testdomain.com
-
  url: /admin/directory/v1/groups/north-vice-president%40testdomain.com/members
  body: '{"email":"garner@testdomain.com","role":"MEMBER","type":"USER"}'
-
  url: /admin/directory/v1/groups/north-vice-president%40testdomain.com/members/garner%40testdomain.com
-
  url: /admin/directory/v1/groups/north-president%40testdomain.com
EOT;

    $requests = $batch->getSimplifiedRequests();
    $this->assertEquals(trim($expected), $this->arrayToYaml($requests));
  }

  public function arrayToYaml($data) {
    // Convert data to YAML
    $dumper = new Dumper();
    $dumper->setIndentation(2);
    return trim($dumper->dump($data, PHP_INT_MAX));
  }
}
