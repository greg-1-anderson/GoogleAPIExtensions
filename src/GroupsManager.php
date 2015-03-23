<?php

namespace Westkingdom\GoogleAPIExtensions;

use WestKingdom\GoogleAPIExtensions\GroupsController;

class GroupsManager {
  protected $existingState = array();
  protected $ctrl;

  /**
   * @param $ctrl control object that does actual actions
   * @param $state initial state
   */
  function __construct(GroupsController $ctrl, $state) {
    $this->ctrl = $ctrl;
    $this->existingState = $this->normalize($state);
  }

  static function createForDomain($applicationName, $domain, $state) {
    $authenticator = ServiceAccountAuthenticator($applicationName);
    $client = $authenticator->authenticate();
    $policy = new StandardGroupPolicy($domain);
    $controller = new GoogleAppsGroupsController($client, $policy);
    $groupManager = new GroupsManager($controller, $currentState);
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
    $this->ctrl->begin();
    //var_export($this->existingState);
    //var_export($memberships);
    $memberships = $this->normalize($memberships);

    foreach ($memberships as $branch => $officesLists) {
      $offices = $officesLists['lists'];
      // Next, update or insert, depending on whether this branch is new.
      if (array_key_exists($branch, $this->existingState)) {
        $this->updateBranch($branch, $offices, $this->existingState[$branch]['lists']);
      }
      else {
        $this->insertBranch($branch, $offices);
      }
    }
    // Finally, delete any branch that is no longer with us.
    foreach ($this->existingState as $branch => $offices) {
      if (!array_key_exists($branch, $memberships)) {
        $this->deleteBranch($branch, $offices);
      }
    }
    $this->existingState = $memberships;
    $this->ctrl->complete();
  }

  function updateBranch($branch, $updateOffices, $existingOffices) {
    foreach ($updateOffices as $officename => $officeData) {
      if (array_key_exists($officename, $existingOffices)) {
        $this->updateOfficeMembers($branch, $officename, $officeData['members'], $existingOffices[$officename]['members']);
        $newAlternateAddresses = $this->getAlternateAddresses($branch, $officename, $officeData);
        $existingAlternateAddresses = $this->getAlternateAddresses($branch, $officename, $existingOffices[$officename]);
        $this->updateOfficeAlternateAddresses($branch, $officename, $newAlternateAddresses, $existingAlternateAddresses);
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
    $this->ctrl->insertBranch($branch);
    $this->updateBranch($branch, $newOffices, array());
  }

  function deleteBranch($branch, $removingOffices) {
    $this->ctrl->deleteBranch($branch);
  }

  function updateOfficeMembers($branch, $officename, $updateMembers, $existingMembers) {
    foreach ($updateMembers as $emailAddress) {
      if (!in_array($emailAddress, $existingMembers)) {
        $this->ctrl->insertMember($branch, $officename, $emailAddress);
      }
    }
    foreach ($existingMembers as $emailAddress) {
      if (!in_array($emailAddress, $updateMembers)) {
        $this->ctrl->removeMember($branch, $officename, $emailAddress);
      }
    }
  }

  function updateOfficeAlternateAddresses($branch, $officename, $newAlternateAddresses, $existingAlternateAddresses) {
    foreach ($newAlternateAddresses as $emailAddress) {
      if (!in_array($emailAddress, $existingAlternateAddresses)) {
        $this->ctrl->insertGroupAlternateAddress($branch, $officename, $emailAddress);
      }
    }
    foreach ($existingAlternateAddresses as $emailAddress) {
      if (!in_array($emailAddress, $newAlternateAddresses)) {
        $this->ctrl->removeGroupAlternateAddress($branch, $officename, $emailAddress);
      }
    }
  }

  function insertOffice($branch, $officename, $officeData) {
    $this->ctrl->insertOffice($branch, $officename, $officeData['properties']);
    $this->updateOfficeMembers($branch, $officename, $officeData['members'], array());
    $newAlternateAddresses = $this->getAlternateAddresses($branch, $officename, $officeData);
    $this->updateOfficeAlternateAddresses($branch, $officename, $newAlternateAddresses, array());
  }

  function deleteOffice($branch, $officename, $officeData) {
    $this->ctrl->deleteOffice($branch, $officename, $officeData['properties']);
  }

  function getAlternateAddresses($branch, $officename, $officeData) {
    $result = array();
    if (isset($officeData['properties']['alternate-addresses'])) {
      $result = (array)$officeData['properties']['alternate-addresses'];
    }
    return $result;
  }

  static function normalize($state) {
    $result = array();
    foreach ($state as $branch => $listsAndAliases) {
      $result[$branch] = GroupsManager::normalizeListsAndAliases($listsAndAliases);
    }
    return $result;
  }

  static function normalizeListsAndAliases($listsAndAliases) {
    // First, normalize the offices data
    $offices = array('lists' => array());
    if (array_key_exists('lists', $listsAndAliases)) {
      $offices['lists'] = GroupsManager::normalizeGroupsData($listsAndAliases['lists']);
    }
    if (array_key_exists('aliases', $listsAndAliases)) {
      $default = array(
        'forward-only' => TRUE,
      );
      $offices['lists'] += GroupsManager::normalizeGroupsData($listsAndAliases['aliases'], $default);
    }
    return $offices;
  }

  /**
   * Convert the alias data into a format that can be merged
   * in with the lists.
   */
  static function normalizeGroupsData($aliasGroups, $default = array()) {
    $result = array();
    foreach ($aliasGroups as $office => $data) {
      $data = GroupsManager::normalizeMembershipData($data);
      $data['properties'] += $default;
      $result[$office] = $data;
    }
    return $result;
  }

  /**
   * Take the membership data and normalize it to always
   * be an associative array with the membership list in
   * an element named 'members'.
   *
   * An array without a 'members' element is presumed to be
   * a list of user email addresses without any additional
   * metadata for the group.
   *
   * A simple string is treated like an array of one element.
   */
  static function normalizeMembershipData($data) {
    if (is_string($data)) {
      return GroupsManager::normalizeMembershipArrayData(array($data));
    }
    else {
      return GroupsManager::normalizeMembershipArrayData($data);
    }
  }

  /**
   * If the array is not associative, then convert it to
   * an array with just a 'members' element containing all
   * of the original data contents.
   */
  static function normalizeMembershipArrayData($data) {
    if (array_key_exists('members', $data)) {
      return $data + array('properties' => array());
    }
    else {
      // TODO: confirm that all of the keys of $data are numeric.
      return array('members' => $data, 'properties' => array());
    }
  }
}
