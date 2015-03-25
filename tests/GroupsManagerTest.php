<?php

use Westkingdom\GoogleAPIExtensions\GroupsManager;
use Westkingdom\GoogleAPIExtensions\Utils;
use Prophecy\PhpUnit\ProphecyTestCase;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Dumper;

class GroupsTestCase extends ProphecyTestCase {

  protected $initialState = array();

  public function setUp() {
    parent::setup();

    $groupData = file_get_contents(dirname(__FILE__) . "/testData/westkingdom.org.yaml");
    $parsed = Yaml::parse($groupData);
    // Throw away the first top-level key, use all of the data under it. Ignore any other top-level keys.
    $this->initialState = Utils::normalize(array_pop($parsed));
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

    $normalized = Utils::normalize($data);

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
    $revealedController = $testController->reveal();
    $journal = new Westkingdom\GoogleAPIExtensions\Journal($revealedController);
    $groupManager = new Westkingdom\GoogleAPIExtensions\GroupsManager($journal, $this->initialState);

    // Prophesize that the new user will be added to the west webministers group.
    $testController->insertMember()->shouldBeCalled()->withArguments(array("west", "webminister", "new.admin@somewhere.com"));
    $testController->insertGroupAlternateAddress()->shouldBeCalled()->withArguments(array("west", "webminister", "webminister@westkingdom.org"));
    $testController->begin()->shouldBeCalled();
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
    $revealedController = $testController->reveal();
    $journal = new Westkingdom\GoogleAPIExtensions\Journal($revealedController);
    $groupManager = new Westkingdom\GoogleAPIExtensions\GroupsManager($journal, $this->initialState);

    // Prophesize that a user will be removed from the west webministers group,
    // and then removed again
    $testController->removeMember()->shouldBeCalled()->withArguments(array("west", "webminister", "robxxx@sca.org"));
    $testController->begin()->shouldBeCalled();
    $testController->complete()->shouldBeCalled();

    // Update the group.  The prophecies are checked against actual
    // behavior during teardown.
    $groupManager->update($newState);
    $groupManager->execute();
  }
}
