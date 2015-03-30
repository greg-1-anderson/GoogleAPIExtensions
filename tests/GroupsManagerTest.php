<?php

use Westkingdom\GoogleAPIExtensions\GroupsManager;
use Westkingdom\GoogleAPIExtensions\StandardGroupPolicy;
use Prophecy\PhpUnit\ProphecyTestCase;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Dumper;

class GroupsTestCase extends ProphecyTestCase {

  protected $initialState = array();
  protected $policy;

  public function setUp() {
    parent::setup();

    $groupData = file_get_contents(dirname(__FILE__) . "/testData/westkingdom.org.yaml");
    $parsed = Yaml::parse($groupData);
    // Throw away the first top-level key, use all of the data under it. Ignore any other top-level keys.
    $this->policy = new StandardGroupPolicy('testdomain.org');
    $this->initialState = $this->policy->normalize(array_pop($parsed));
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

  public function assertYamlEquals($data, $expected) {
    $this->assertEquals($this->arrayToYaml($data), $this->arrayToYaml($expected));
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
    $this->assertEquals(implode(',', array_keys($this->initialState)), 'west,mists');
  }

  public function testInsertMember() {
    // Do a nominal test to check to see that our test data loaded
    $this->assertEquals(implode(',', array_keys($this->initialState)), 'west,mists');

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
    $testController->insertMember()->shouldBeCalled()->withArguments(array("west", "webminister", "west-webminister@testdomain.org", "new.admin@somewhere.com"));
    $testController->insertGroupAlternateAddress()->shouldBeCalled()->withArguments(array("west", "webminister", "west-webminister@testdomain.org", "webminister@westkingdom.org"));
    $testController->verifyMember()->shouldBeCalled()->withArguments(array("west", "webminister", "west-webminister@testdomain.org", "new.admin@somewhere.com"));
    $testController->verifyGroupAlternateAddress()->shouldBeCalled()->withArguments(array("west", "webminister", "west-webminister@testdomain.org", "webminister@westkingdom.org"));
    $testController->complete()->shouldBeCalled();

    // Update the group.  The prophecies are checked against actual
    // behavior during teardown.
    $groupManager->update($newState);
    $groupManager->execute();
  }

  public function testRemoveMember() {
    // Do a nominal test to check to see that our test data loaded
    $this->assertEquals(implode(',', array_keys($this->initialState)), 'west,mists');

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
    $testController->removeMember()->shouldBeCalled()->withArguments(array("west", "webminister", "west-webminister@testdomain.org", "robxxx@sca.org"));
    $testController->complete()->shouldBeCalled();

    // Update the group.  The prophecies are checked against actual
    // behavior during teardown.
    $groupManager->update($newState);
    $groupManager->execute();
  }
}
