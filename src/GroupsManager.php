<?php

namespace Westkingdom\GoogleAPIExtensions;

use Westkingdom\GoogleAPIExtensions\Internal\Journal;

class GroupsManager {
  protected $ctrl;
  protected $policy;
  protected $journal;

  /**
   * @param $ctrl control object that does actual actions
   * @param $state initial state
   */
  function __construct(GroupsController $ctrl, $policy, $state) {
    $this->ctrl = $ctrl;
    $this->policy = $policy;
    $this->journal = new Journal($ctrl, $state);
  }

  static function createForDomain($applicationName, $domain, $state) {
    $authenticator = ServiceAccountAuthenticator($applicationName);
    $client = $authenticator->authenticate();
    $policy = new StandardGroupPolicy($domain);
    $controller = new GoogleAppsGroupsController($client);
    $groupManager = new GroupsManager($controller, $policy, $currentState);
    return $groupManager;
  }

  /**
   * Update our group memberships
   *
   *
   * @param $memberships nested associative array
   *    BRANCHES contain LISTS and ALIASES.
   *
   *      branchname => array(lists => ..., aliases=> ...)
   *
   *      LISTS contain groups.
   *
   *        lists => array(group1 => ..., group2 => ...)
   *
   *          Groups can be one of three formats:
   *
   *            string - a group with one member; data is email address
   *
   *            simple array of strings - email addresses of members
   *
   *            associative array - element 'members' contains email addresses of members
   *
   *      ALIASES are structured just like groups.
   *
   *    The difference between a LIST and an ALIAS is that a list is
   *    expected to keep an archive of all email that is sent to it,
   *    and an alias just passes the email through.
   */
  function update($memberships) {
    $existingState = $this->journal->getExistingState();
    $this->journal->begin();

    foreach ($memberships as $branch => $officesLists) {
      $offices = $officesLists['lists'];
      // Next, update or insert, depending on whether this branch is new.
      if (array_key_exists($branch, $existingState)) {
        $this->updateBranch($branch, $offices, $existingState[$branch]['lists']);
      }
      else {
        $this->insertBranch($branch, $offices);
      }
    }
    // Finally, delete any branch that is no longer with us.
    foreach ($existingState as $branch => $offices) {
      if (!array_key_exists($branch, $memberships)) {
        $this->deleteBranch($branch, $offices);
      }
    }
    $this->journal->complete();
  }

  function updateBranch($branch, $updateOffices, $existingOffices) {
    foreach ($updateOffices as $officename => $officeData) {
      if (array_key_exists($officename, $existingOffices)) {
        $this->updateOfficeMembers($branch, $officename, $officeData['properties']['group-id'], $officeData['members'], $existingOffices[$officename]['members']);
        $newAlternateAddresses = GroupsManager::getAlternateAddresses($branch, $officename, $officeData);
        $existingAlternateAddresses = GroupsManager::getAlternateAddresses($branch, $officename, $existingOffices[$officename]);
        $this->updateOfficeAlternateAddresses($branch, $officename, $officeData['properties']['group-id'], $newAlternateAddresses, $existingAlternateAddresses);
      }
      else {
        $this->insertOffice($branch, $officename, $officeData);
      }
    }
    foreach ($existingOffices as $officename => $officeData) {
      if (!array_key_exists($officename, $updateOffices)) {
        $this->deleteOffice($branch, $officename, $officeData);
      }
    }
  }

  function insertBranch($branch, $newOffices) {
    $this->journal->insertBranch($branch);
    $this->updateBranch($branch, $newOffices, array());
  }

  function deleteBranch($branch, $removingOffices) {
    $this->journal->deleteBranch($branch);
  }

  function updateOfficeMembers($branch, $officename, $groupId, $updateMembers, $existingMembers) {
    foreach ($updateMembers as $emailAddress) {
      if (!in_array($emailAddress, $existingMembers)) {
        $this->journal->insertMember($branch, $officename, $groupId, $emailAddress);
      }
    }
    foreach ($existingMembers as $emailAddress) {
      if (!in_array($emailAddress, $updateMembers)) {
        $this->journal->removeMember($branch, $officename, $groupId, $emailAddress);
      }
    }
  }

  function updateOfficeAlternateAddresses($branch, $officename, $groupId, $newAlternateAddresses, $existingAlternateAddresses) {
    foreach ($newAlternateAddresses as $emailAddress) {
      if (!in_array($emailAddress, $existingAlternateAddresses)) {
        $this->journal->insertGroupAlternateAddress($branch, $officename, $groupId, $emailAddress);
      }
    }
    foreach ($existingAlternateAddresses as $emailAddress) {
      if (!in_array($emailAddress, $newAlternateAddresses)) {
        $this->journal->removeGroupAlternateAddress($branch, $officename, $groupId, $emailAddress);
      }
    }
  }

  function insertOffice($branch, $officename, $officeData) {
    $this->journal->insertOffice($branch, $officename, $officeData['properties']);
    $this->updateOfficeMembers($branch, $officename, $officeData['properties']['group-id'], $officeData['members'], array());
    $newAlternateAddresses = GroupsManager::getAlternateAddresses($branch, $officename, $officeData['properties']['group-id'], $officeData);
    $this->updateOfficeAlternateAddresses($branch, $officename, $officeData['properties']['group-id'], $newAlternateAddresses, array());
  }

  function deleteOffice($branch, $officename, $officeData) {
    $this->journal->deleteOffice($branch, $officename, $officeData['properties']);
  }

  static function getAlternateAddresses($branch, $officename, $officeData) {
    $result = array();
    if (isset($officeData['properties']['alternate-addresses'])) {
      $result = (array)$officeData['properties']['alternate-addresses'];
    }
    return $result;
  }

  function execute() {
    $this->journal->execute();
    return $this->journal->getExistingState();
  }
}
