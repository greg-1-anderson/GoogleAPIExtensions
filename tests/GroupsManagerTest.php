<?php

use Westkingdom\GoogleAPIExtensions\GroupsManager;
use Westkingdom\GoogleAPIExtensions\StandardGroupPolicy;
use Prophecy\PhpUnit\ProphecyTestCase;
use Prophecy\Argument\Token\AnyValueToken;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Dumper;

class GroupsManagerTestCase extends ProphecyTestCase {

  protected $initialState = array();
  protected $policy;

  public function setUp() {
    parent::setup();

    $groupData = "
west:
  lists:
    webminister:
      members:
        - minister@sca.org
        - deputy@sca.org
      properties:
        group-name: West Kingdom Web Minister
_aggregated:
  lists:
    all-webministers:
      members:
        - west-webminister@testdomain.org
    west-officers:
      members:
        - west-webminister@testdomain.org";
    $this->initialState = Yaml::parse(trim($groupData));
    $this->policy = new StandardGroupPolicy('testdomain.org');
  }

  public function testNormalize() {
    $data = Yaml::parse("
north:
  lists:
    president:
      - bill@testdomain.org
    vice-president:
      - walter@testdomain.org
    secretary:
      - george@testdomain.org
  aliases:
    officers:
      - president@north.testdomain.org
      - secretary@north.testdomain.org");

    $normalized = $this->policy->normalize($data);

    $expected = "
north:
  lists:
    president:
      members:
        - bill@testdomain.org
      properties:
        group-email: north-president@testdomain.org
        group-id: north-president@testdomain.org
        group-name: 'North President'
    vice-president:
      members:
        - walter@testdomain.org
      properties:
        group-email: north-vicepresident@testdomain.org
        group-id: north-vicepresident@testdomain.org
        group-name: 'North Vice-president'
    secretary:
      members:
        - george@testdomain.org
      properties:
        group-email: north-secretary@testdomain.org
        group-id: north-secretary@testdomain.org
        group-name: 'North Secretary'
    officers:
      members:
        - president@north.testdomain.org
        - secretary@north.testdomain.org
      properties:
        forward-only: true
        group-email: north-officers@testdomain.org
        group-id: north-officers@testdomain.org
        group-name: 'North Officers'";

    $this->assertYamlEquals(trim($expected), $normalized);
  }

  public function assertYamlEquals($expected, $data) {
    $this->assertEquals($this->arrayToYaml($expected), $this->arrayToYaml($data));
  }

  public function arrayToYaml($data) {
    if (is_string($data)) {
      return trim($data);
    }
    else {
      // Convert data to YAML
      $dumper = new Dumper();
      $dumper->setIndentation(2);
      return trim($dumper->dump($data, PHP_INT_MAX));
    }
  }

  public function testLoadingOfTestData() {
    // Do a nominal test to check to see that our test data loaded
    $this->assertEquals('west,_aggregated', implode(',', array_keys($this->initialState)));
  }

  public function testInsertMember() {
    // Create a new state object by copying our existing state and adding
    // a member to the "west-webminister" group.
    $newState = $this->initialState;
    $newState['west']['lists']['webminister']['members'][] = 'new.admin@somewhere.com';
    $newState['west']['lists']['webminister']['properties']['alternate-addresses'] = 'webminister@westkingdom.org';

    // Create a new test controller prophecy, and reveal it to the
    // Groups object we are going to test.
    $testController = $this->prophesize('Westkingdom\GoogleAPIExtensions\GroupsController');
    $groupManager = new GroupsManager($testController->reveal(), $this->policy, $this->initialState);

    // Prophesize that the new user will be added to the west webministers group.
    $testController->begin()->shouldBeCalled();
    $testController->insertMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "webminister", "west-webminister@testdomain.org", "new.admin@somewhere.com"));
    $testController->insertGroupAlternateAddress()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "webminister", "west-webminister@testdomain.org", "webminister@westkingdom.org"));
//    $testController->verifyMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "webminister", "west-webminister@testdomain.org", "new.admin@somewhere.com"));
//    $testController->verifyGroupAlternateAddress()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "webminister", "west-webminister@testdomain.org", "webminister@westkingdom.org"));
    $testController->complete()->shouldBeCalled()->withArguments(array(TRUE));

    // Update the group.  The prophecies are checked against actual
    // behavior during teardown.
    $groupManager->update($newState);
    $groupManager->execute();

    $expectedFinalState = "
west:
  lists:
    webminister:
      members:
        - minister@sca.org
        - deputy@sca.org
      properties:
        group-name: 'West Kingdom Web Minister'
_aggregated:
  lists:
    all-webministers:
      members:
        - west-webminister@testdomain.org
    west-officers:
      members:
        - west-webminister@testdomain.org
'#queues':
  default:
    -
      run-function: insertMember
      run-params:
        - west
        - webminister
        - west-webminister@testdomain.org
        - new.admin@somewhere.com
      verify-function: verifyMember
    -
      run-function: insertGroupAlternateAddress
      run-params:
        - west
        - webminister
        - west-webminister@testdomain.org
        - webminister@westkingdom.org
      verify-function: verifyGroupAlternateAddress";

    $state = $groupManager->export();
    $this->assertEquals(trim($expectedFinalState), $this->arrayToYaml($state));
  }

  public function testRemoveMember() {
    // Create a new state object by copying our existing state and adding
    // a member to the "west-webminister" group.
    $newState = $this->initialState;
    array_pop($newState['west']['lists']['webminister']['members']);

    // Create a new test controller prophecy, and reveal it to the
    // Groups object we are going to test.
    $testController = $this->prophesize('Westkingdom\GoogleAPIExtensions\GroupsController');
    $groupManager = new GroupsManager($testController->reveal(), $this->policy, $this->initialState);

    // Prophesize that a user will be removed from the west webministers group,
    // and then removed again
    $testController->begin()->shouldBeCalled();
    $testController->removeMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "webminister", "west-webminister@testdomain.org", "deputy@sca.org"));
    $testController->complete()->shouldBeCalled()->withArguments(array(TRUE));

    // Update the group.  The prophecies are checked against actual
    // behavior during teardown.
    $groupManager->update($newState);
    $groupManager->execute();
  }

  public function testInsertOffice() {
    // Create a new state object by copying our existing state and adding
    // a member to the "west-webminister" group.
    $newState = $this->initialState;
    $newState['west']['lists']['seneschal']['members'][] = 'anne@kingdom.org';
    $newState['west']['lists']['seneschal']['properties']['group-name'] = 'West Seneschal';

    // Create a new test controller prophecy, and reveal it to the
    // Groups object we are going to test.
    $testController = $this->prophesize('Westkingdom\GoogleAPIExtensions\GroupsController');
    $groupManager = new GroupsManager($testController->reveal(), $this->policy, $this->initialState);

    // Prophesize that the new user will be added to the west webministers group.
    $testController->begin()->shouldBeCalled();

    $testController->insertOffice()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "seneschal", array("group-name" => "West Seneschal", "group-email" => "west-seneschal@testdomain.org", "group-id" => "west-seneschal@testdomain.org")));
    $testController->configureOffice()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "seneschal", array("group-name" => "West Seneschal", "group-email" => "west-seneschal@testdomain.org", "group-id" => "west-seneschal@testdomain.org")));
//    $testController->verifyOffice()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "seneschal", array("group-name" => "West Seneschal", "group-email" => "west-seneschal@testdomain.org", "group-id" => "west-seneschal@testdomain.org")));
//    $testController->verifyOfficeConfiguration()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "seneschal", array("group-name" => "West Seneschal", "group-email" => "west-seneschal@testdomain.org", "group-id" => "west-seneschal@testdomain.org")));
    $testController->insertMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "seneschal", "west-seneschal@testdomain.org", "anne@kingdom.org"));
//    $testController->verifyMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "seneschal", "west-seneschal@testdomain.org", "anne@kingdom.org"));

//    $testController->insertOffice()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "all-seneschals", array("group-id" => "all-seneschals@testdomain.org", "group-name" => "All Seneschals", "group-email" => "all-seneschals@testdomain.org")));
//    $testController->configureOffice()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "all-seneschals", array("group-id" => "all-seneschals@testdomain.org", "group-name" => "All Seneschals", "group-email" => "all-seneschals@testdomain.org")));
//    $testController->verifyOffice()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "all-seneschals", array("group-id" => "all-seneschals@testdomain.org", "group-name" => "All Seneschals", "group-email" => "all-seneschals@testdomain.org")));
//    $testController->verifyOfficeConfiguration()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "all-seneschals", array("group-id" => "all-seneschals@testdomain.org", "group-name" => "All Seneschals", "group-email" => "all-seneschals@testdomain.org")));
//    $testController->insertMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "all-seneschals", "all-seneschals@testdomain.org", "west-seneschal@testdomain.org"));
//    $testController->verifyMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "all-seneschals", "all-seneschals@testdomain.org", "west-seneschal@testdomain.org"));

//    $testController->insertMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "west-officers", "west-officers@testdomain.org", "west-seneschal@testdomain.org"));
//    $testController->verifyMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "west-officers", "west-officers@testdomain.org", "west-seneschal@testdomain.org"));

    $testController->complete()->shouldBeCalled()->withArguments(array(TRUE));

    // Update the group.  The prophecies are checked against actual
    // behavior during teardown.
    $groupManager->update($newState);
    $groupManager->execute();
  }


}
