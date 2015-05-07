<?php

use Westkingdom\GoogleAPIExtensions\GroupsManager;
use Westkingdom\GoogleAPIExtensions\StandardGroupPolicy;
use Westkingdom\GoogleAPIExtensions\Internal\Journal;

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
        group-name: West Kingdom Web Minister";
    $this->initialState = Yaml::parse(trim($groupData));

    $properties = array(
      'top-level-group' => 'north',
      'subdomains' => 'fogs,geese,wolves,lightwoods',
    );
    $this->policy = new StandardGroupPolicy('testdomain.org', $properties);
  }

  public function testAggregatedSubgroups() {
    $this->assertTrue($this->policy->isSubdomain('fogs'));
    $this->assertTrue($this->policy->isSubdomain('wolves'));

    $this->assertYamlEquals("- president@fogs.testdomain.org", $this->policy->getGroupDefaultAlternateAddresses('fogs', 'president'));

    $data = Yaml::parse("
north:
  lists:
    president:
      members:
        - bill@testdomain.org
  subgroups:
    - fogs
    - geese
    - wolves
fogs:
  lists:
    president:
      members:
        - ron@testdomain.org
  subgroups:
    - lightwoods
geese:
  lists:
    president:
      members:
        - george@testdomain.org
  subgroups:
    - gustyplains
wolves:
  lists:
    president:
      members:
        - frank@testdomain.org
  subgroups:
    - coldholm
lightwoods:
  lists:
    president:
      members:
        - alex@testdomain.org
  subgroups:
    - seamountain
gustyplains:
  lists:
    president:
      members:
        - tom@testdomain.org
  subgroups: {  }
coldholm:
  lists:
    president:
      members:
        - richard@testdomain.org
  subgroups: {  }
seamountain:
  lists:
    president:
      members:
        - gerald@testdomain.org
  subgroups: {  }");

    $data = $this->policy->normalize($data);

    // Strip out 'members' and 'subgroups', as we are only
    // testing the properties here.
    $reducedData = array();
    foreach ($data as $branch => $branchinfo) {
      foreach ($branchinfo['lists'] as $office => $officeinfo) {
        $reducedData[$branch]['lists'][$office]['properties'] = $officeinfo['properties'];
      }
    }

    $expected = "
north:
  lists:
    president:
      properties:
        group-email: north-president@testdomain.org
        group-id: north-president@testdomain.org
        group-name: 'North President'
        alternate-addresses:
          - president@testdomain.org
fogs:
  lists:
    president:
      properties:
        group-email: fogs-president@testdomain.org
        group-id: fogs-president@testdomain.org
        group-name: 'Fogs President'
        alternate-addresses:
          - president@fogs.testdomain.org
geese:
  lists:
    president:
      properties:
        group-email: geese-president@testdomain.org
        group-id: geese-president@testdomain.org
        group-name: 'Geese President'
        alternate-addresses:
          - president@geese.testdomain.org
wolves:
  lists:
    president:
      properties:
        group-email: wolves-president@testdomain.org
        group-id: wolves-president@testdomain.org
        group-name: 'Wolves President'
        alternate-addresses:
          - president@wolves.testdomain.org
lightwoods:
  lists:
    president:
      properties:
        group-email: lightwoods-president@testdomain.org
        group-id: lightwoods-president@testdomain.org
        group-name: 'Lightwoods President'
        alternate-addresses:
          - president@lightwoods.testdomain.org
gustyplains:
  lists:
    president:
      properties:
        group-email: gustyplains-president@testdomain.org
        group-id: gustyplains-president@testdomain.org
        group-name: 'Gustyplains President'
coldholm:
  lists:
    president:
      properties:
        group-email: coldholm-president@testdomain.org
        group-id: coldholm-president@testdomain.org
        group-name: 'Coldholm President'
seamountain:
  lists:
    president:
      properties:
        group-email: seamountain-president@testdomain.org
        group-id: seamountain-president@testdomain.org
        group-name: 'Seamountain President'";
    $this->assertYamlEquals(trim($expected), $reducedData);

    $testController = $this->prophesize('Westkingdom\GoogleAPIExtensions\GroupsController');
    $groupManager = new GroupsManager($testController->reveal(), $this->policy, $data);

    $data = $this->policy->generateParentage($data);
    $result = $this->policy->generateAggregatedGroups($data);

    $expected = "
all-presidents:
  properties:
    group-id: all-presidents@testdomain.org
    group-name: 'All Presidents'
    group-email: all-presidents@testdomain.org
  members:
    - north-president@testdomain.org
    - fogs-president@testdomain.org
    - geese-president@testdomain.org
    - wolves-president@testdomain.org
    - lightwoods-president@testdomain.org
    - gustyplains-president@testdomain.org
    - coldholm-president@testdomain.org
    - seamountain-president@testdomain.org
fogs-all-presidents:
  properties:
    group-id: fogs-all-presidents@testdomain.org
    group-name: 'All Fogs Presidents'
    group-email: fogs-all-presidents@testdomain.org
  members:
    - fogs-president@testdomain.org
    - lightwoods-president@testdomain.org
    - seamountain-president@testdomain.org
geese-all-presidents:
  properties:
    group-id: geese-all-presidents@testdomain.org
    group-name: 'All Geese Presidents'
    group-email: geese-all-presidents@testdomain.org
  members:
    - geese-president@testdomain.org
    - gustyplains-president@testdomain.org
wolves-all-presidents:
  properties:
    group-id: wolves-all-presidents@testdomain.org
    group-name: 'All Wolves Presidents'
    group-email: wolves-all-presidents@testdomain.org
  members:
    - wolves-president@testdomain.org
    - coldholm-president@testdomain.org
lightwoods-all-presidents:
  properties:
    group-id: lightwoods-all-presidents@testdomain.org
    group-name: 'All Lightwoods Presidents'
    group-email: lightwoods-all-presidents@testdomain.org
  members:
    - lightwoods-president@testdomain.org
    - seamountain-president@testdomain.org";

    $this->assertYamlEquals(trim($expected), $result);
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
    $this->assertEquals('west', implode(',', array_keys($this->initialState)));
  }

  public function testImportOperations() {
    // Test importing of queues.  The two identical items should
    // be noticed, and the second discarded.
    $initial = Yaml::parse(trim("
'#queues':
  create:
    -
      run-function: insertOffice
      run-params:
        - _aggregated
        - all-rapiermarshals
        -
          group-id: all-rapiermarshals@westkingdom.org
          group-name: 'All Rapier-marshals'
          group-email: all-rapiermarshals@westkingdom.org
      verify-function: verifyOffice
    -
      run-function: insertOffice
      run-params:
        - _aggregated
        - all-rapiermarshals
        -
          group-id: all-rapiermarshals@westkingdom.org
          group-name: 'All Rapier-marshals'
          group-email: all-rapiermarshals@westkingdom.org
      verify-function: verifyOffice"));

    $testController = $this->prophesize('Westkingdom\GoogleAPIExtensions\GroupsController');
    $groupManager = new GroupsManager($testController->reveal(), $this->policy, $initial);

    $exported = $groupManager->export();

    // Note: we fail verification here because we verify all ops on import,
    // and we have mocked the group controller, but not its verify function,
    // so it cannot verify.
    $expected = "
create:
  -
    run-function: insertOffice
    run-params:
      - _aggregated
      - all-rapiermarshals
      -
        group-id: all-rapiermarshals@westkingdom.org
        group-name: 'All Rapier-marshals'
        group-email: all-rapiermarshals@westkingdom.org
    verify-function: verifyOffice
    state:
      failedVerification: true";

    $this->assertEquals(trim($expected), $this->arrayToYaml($exported['#queues']));
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
    $testController->verifyMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "webminister", "west-webminister@testdomain.org", "new.admin@somewhere.com"));
    $testController->verifyGroupAlternateAddress()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "webminister", "west-webminister@testdomain.org", "webminister@westkingdom.org"));
    $testController->complete()->shouldBeCalled()->withArguments(array(TRUE));

    // Update the group.  The prophecies are checked against actual
    // behavior during teardown.
    $groupManager->update($newState);
    $groupManager->execute();

    // Note: we fail verification because we have mocked the group
    // controller, but not its verify function, so it cannot verify.
    $expectedFinalState = "
west:
  lists:
    webminister:
      members:
        - deputy@sca.org
        - minister@sca.org
      properties:
        group-name: 'West Kingdom Web Minister'
_aggregated:
  lists: {  }
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
      state:
        failedVerification: true
    -
      run-function: insertGroupAlternateAddress
      run-params:
        - west
        - webminister
        - west-webminister@testdomain.org
        - webminister@westkingdom.org
      verify-function: verifyGroupAlternateAddress
      state:
        failedVerification: true";

    $state = $groupManager->export();
    $this->assertEquals(trim($expectedFinalState), $this->arrayToYaml($state));
  }

  public function testRemoveMember() {
    // Create a new state object by copying our existing state and adding
    // a member to the "west-webminister" group.
    $newState = $this->initialState;
    $removed = array_pop($newState['west']['lists']['webminister']['members']);

    // Create a new test controller prophecy, and reveal it to the
    // Groups object we are going to test.
    $testController = $this->prophesize('Westkingdom\GoogleAPIExtensions\GroupsController');
    $revealedController = $testController->reveal();
    $journal = new Journal($revealedController, $this->initialState);
    $groupManager = new GroupsManager($revealedController, $this->policy, $this->initialState, $journal);

    // Prophesize that a user will be removed from the west webministers group,
    // and then removed again
    $testController->begin()->shouldBeCalled();
    $testController->removeMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "webminister", "west-webminister@testdomain.org", $removed));
    $testController->complete()->shouldBeCalled()->withArguments(array(TRUE));

    // Update the group.  The prophecies are checked against actual
    // behavior during teardown.
    $groupManager->update($newState);
    $groupManager->execute();

    // Again, we have mocked the group controller, so verification is not done.
    // If the controller did verify, then it would call the following function
    $journal->removeMemberVerified(NULL, "west", "webminister", "west-webminister@testdomain.org", $removed);

    $expectedFinalState = "
west:
  lists:
    webminister:
      members:
        - minister@sca.org
      properties:
        group-name: 'West Kingdom Web Minister'";

    $state = $groupManager->export();
    unset($state['#queues']);
    $this->assertEquals(trim($expectedFinalState), $this->arrayToYaml($state));

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
    $revealedController = $testController->reveal();
    $journal = new Journal($revealedController, $this->initialState);
    $groupManager = new GroupsManager($revealedController, $this->policy, $this->initialState, $journal);

    // Prophesize that the new user will be added to the west webministers group.
    $testController->begin()->shouldBeCalled();

    $testController->insertOffice()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "seneschal", array("group-name" => "West Seneschal", "group-email" => "west-seneschal@testdomain.org", "group-id" => "west-seneschal@testdomain.org")));
    $testController->configureOffice()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "seneschal", array("group-name" => "West Seneschal", "group-email" => "west-seneschal@testdomain.org", "group-id" => "west-seneschal@testdomain.org")));
    $testController->verifyOffice()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "seneschal", array("group-name" => "West Seneschal", "group-email" => "west-seneschal@testdomain.org", "group-id" => "west-seneschal@testdomain.org")));
    $testController->verifyOfficeConfiguration()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "seneschal", array("group-name" => "West Seneschal", "group-email" => "west-seneschal@testdomain.org", "group-id" => "west-seneschal@testdomain.org")));
    $testController->insertMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "seneschal", "west-seneschal@testdomain.org", "anne@kingdom.org"));
    $testController->verifyMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "west", "seneschal", "west-seneschal@testdomain.org", "anne@kingdom.org"));
    $testController->insertOffice()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "west-officers", array("group-name" => "West Officers", "group-email" => "west-officers@testdomain.org", "group-id" => "west-officers@testdomain.org")));
    $testController->configureOffice()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "west-officers", array("group-name" => "West Officers", "group-email" => "west-officers@testdomain.org", "group-id" => "west-officers@testdomain.org")));
    $testController->verifyOfficeConfiguration()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "west-officers", array("group-name" => "West Officers", "group-email" => "west-officers@testdomain.org", "group-id" => "west-officers@testdomain.org")));
    $testController->verifyOffice()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "west-officers", array("group-name" => "West Officers", "group-email" => "west-officers@testdomain.org", "group-id" => "west-officers@testdomain.org")));
    $testController->insertMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "west-officers", "west-officers@testdomain.org", "west-webminister@testdomain.org"));
    $testController->insertMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "west-officers", "west-officers@testdomain.org", "west-seneschal@testdomain.org"));
    $testController->verifyMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "west-officers", "west-officers@testdomain.org", "west-webminister@testdomain.org"));
    $testController->verifyMember()->shouldBeCalled()->withArguments(array(new AnyValueToken(), "_aggregated", "west-officers", "west-officers@testdomain.org", "west-seneschal@testdomain.org"));

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

    // Force the call to insertOfficeVerified, so we can test the generation
    // of aggregated groups.
    $journal->insertOfficeVerified(array(), 'west', 'seneschal', $newState['west']['lists']['seneschal']['properties']);

    // Call 'execute' again, to insure that updateAggregated() is called.
    $groupManager->execute();

    // We don't see the aggregated group here, because the verify functions
    // are never called (due to the mocked controller), so the verified()
    // functions are never called, and these are what update the state.
    $expectedFinalState = "
west:
  lists:
    seneschal:
      properties:
        group-name: 'West Seneschal'
    webminister:
      members:
        - deputy@sca.org
        - minister@sca.org
      properties:
        group-name: 'West Kingdom Web Minister'";

    $state = $groupManager->export();
    unset($state['#queues']);
    $this->assertEquals(trim($expectedFinalState), $this->arrayToYaml($state));

  }
}
