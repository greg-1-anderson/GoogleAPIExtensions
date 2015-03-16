<?php

use Westkingdom\GoogleAPIExtensions\Groups;
use Prophecy\PhpUnit\ProphecyTestCase;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Dumper;

class GroupsTestCase extends ProphecyTestCase {

  protected $existingState = array();

  public function setUp() {
    parent::setup();

    $groupData = file_get_contents(dirname(__FILE__) . "/testData/westkingdom.org.yaml");
    $parsed = Yaml::parse($groupData);
    // Throw away the first top-level key, use all of the data under it. Ignore any other top-level keys.
    $this->existingState = Groups::normalize(array_pop($parsed));
  }

  public function testNormalize() {
    $data = Yaml::parse("
north:
  lists:
    president:
      - bill@whitehouse.gov
    secretary:
      - george@whitehouse.gov
  aliases:
    all:
      - president@north.whitehouse.gov
      - secretary@north.whitehouse.gov");

    $normalized = Groups::normalize($data);

    $expected = "
north:
  lists:
    president:
      members:
        - bill@whitehouse.gov
      properties: {  }
    secretary:
      members:
        - george@whitehouse.gov
      properties: {  }
    all:
      members:
        - president@north.whitehouse.gov
        - secretary@north.whitehouse.gov
      properties:
        forward-only: true";

    $this->assertYamlEquals($normalized, trim($expected));
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

  public function testGroupUpdate() {
    // Do a nominal test to check to see that our test data loaded
    $this->assertEquals(implode(',', array_keys($this->existingState)), 'west,mists');

    // Create a new state object by copying our existing state and adding
    // a member to the "west-webminister" group.
    $newState = $this->existingState;
    $newState['west']['lists']['webminister']['members'][] = 'new.admin@somewhere.com';

    // Create a new test controller prophecy, and reveal it to the
    // Groups object we are going to test.
    $testController = $this->prophesize('Westkingdom\GoogleAPIExtensions\GroupsController');
    $groupManager = new Westkingdom\GoogleAPIExtensions\Groups($testController->reveal(), $this->existingState);

    // Prophesize that the new user will be added to the west webministers group.
    // insertMember("west", "webminister", 'new.admin@somewhere.com');
    $testController->insertMember()->shouldBeCalled();

    // Run the tests.  The prophecies are checked against actual
    // behavior during teardown.
    $groupManager->update($newState);

  }

}
