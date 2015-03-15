<?php

namespace Westkingdom\GoogleAPIExtensions;

use WestKingdom\GoogleAPIExtensions\GroupsController;

class Groups {
  protected $existingState = array();
  protected $ctrl;

  /**
   * @param $ctrl control object that does actual actions
   * @param $state initial state
   */
  function __construct(GroupsController $ctrl, $state) {
    $this->ctrl = $ctrl;
    $this->existingState = $state;
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
    //var_export($this->existingState);
    //var_export($memberships);

    foreach ($memberships as $branch => $officesListsAndAliases) {
      // First, normalize the offices data
      $offices = array();
      if (array_key_exists('lists', $officesListsAndAliases)) {
        $offices = $officesListsAndAliases['lists'];
      }
      if (array_key_exists('aliases', $offices)) {
        $offices += $this->normalizeAliasData($officesListsAndAliases['aliases']);
      }

      // Next, update or insert, depending on whether this branch is new.
      if (array_key_exists($branch, $this->existingState)) {
        $this->updateBranch($branch, $offices, $this->existingState[$branch]);
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
  }

  function updateBranch($branch, $updateOffices, $existingOffices) {
    foreach ($updateOffices as $officename => $members) {
      $members = $this->normalizeMembershipData($members);
      if (array_key_exists($officename, $existingOffices)) {
        $this->updateOffice($branch, $officename, $members, $existingOffices[$officename]);
      }
      else {
        $this->insertOffice($branch, $officename, $members);
      }
    }
    foreach ($existingOffices as $officename => $members) {
      if (!array_key_exists($officenmae, $updateOffices)) {
        $this->deleteOffice($branch, $officename, $members);
      }
    }
  }

  function insertBranch($branch, $newOffices) {
    $this->ctrl->insertBranch($branch);
    $this->updateBranch($branch, $newOffices, array());
  }

  function deleteBranch($branch, $removingOffices) {
    $this->updateBranch($branch, array(), $removingOffices);
    $this->ctrl->deleteBranch($branch);
  }

  function updateOffice($branch, $officename, $updateMembers, $existingMembers) {
    foreach ($updateMembers['members'] as $emailAddress) {
      if (!in_array($emailAddress, $existingMembers['members'])) {
        $this->ctrl->insertMember($branch, $officename, $emailAddress);
      }
    }
    foreach ($existingMembers['members'] as $emailAddress) {
      if (!in_array($emailAddress, $updateMembers['members'])) {
        $this->ctrl->removeMember($branch, $officename, $emailAddress);
      }
    }
  }

  function insertOffice($branch, $officename, $newMembers) {
    $this->ctrl->insertOffice($branch, $officename, $newMembers['settings']);
    $this->updateOffice($branch, $officename, $newMembers, array());
  }

  function deleteOffice($branch, $officename, $removingMembers) {
    $this->updateOffice($branch, $officename, array(), $removeingMembers);
    $this->ctrl->deleteOffice($branch, $officename, $removingMembers['settings']);
  }

  /**
   * Convert the alias data into a format that can be merged
   * in with the lists.
   */
  function normalizeAliasData($aliasGroups) {
    $result = array();
    foreach ($aliasGroups as $office => $data) {
      $data = $this->normalizeMembershipData($data);
      $data['settings']['forward-only'] = TRUE;
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
  function normalizeMembershipData($data) {
    if (is_string($data)) {
      return $this->normalizeMembershipArrayData(array($data));
    }
    else {
      return $this->normalizeMembershipArrayData(array($data));
    }
  }

  /**
   * If the array is not associative, then convert it to
   * an array with just a 'members' element containing all
   * of the original data contents.
   */
  function normalizeMembershipArrayData($data) {
    if (array_key_exists('members', $data)) {
      return $data + array('settings' => array());
    }
    else {
      // TODO: confirm that all of the keys of $data are numeric.
      return array('members' => $data, 'settings' => array());
    }
  }
}
