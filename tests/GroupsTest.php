<?php

use Westkingdom\GoogleAPIExtensions\Groups;
use Prophecy\PhpUnit\ProphecyTestCase;
use Symfony\Component\Yaml\Yaml;

class GroupsTestCase extends ProphecyTestCase {

  protected $existingState = array();

  public function setUp() {
    parent::setup();

    $groupData = file_get_contents(dirname(__FILE__) . "/testData/westkingdom.org.yaml");
    $parsed = Yaml::parse($groupData);
    // Throw away the first top-level key, use all of the data under it. Ignore any other top-level keys.
    $this->existingState = array_pop($parsed);
  }

  public function testGroupUpdate()
  {
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
