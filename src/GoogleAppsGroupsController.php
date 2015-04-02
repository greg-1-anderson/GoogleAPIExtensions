<?php

namespace Westkingdom\GoogleAPIExtensions;

/**
 * Use this groups controller with Westkingdom\GoogleAPIExtensions\Groups
 * to update groups and group memberships in Google Apps.
 *
 * Batch mode is always used.  You may provide your own batch object,
 * in which case you should call $client->setUseBatch(true) and
 * $batch->execute() yourself.  If you do not provide a batch object,
 * then one will be created in the constructor for you, and its
 * execute() method will be called at the end of the update.
 */
class GoogleAppsGroupsController implements GroupsController {
  protected $client;
  protected $batch;
  protected $directoryService;
  protected $groupSettingsService;
  protected $autoExecute = FALSE;

  /**
   * @param $client Google Apps API client object
   * @param $batch Google Apps batch object. Optional; one will be created
   * if none provided.
   */
  function __construct($client, $batch = NULL) {
    $this->client = $client;
    $this->batch = $batch;
    if (!isset($batch)) {
      $this->batch = new \Google_Http_Batch($client);
      $this->autoExecute = TRUE;
    }
    $this->directoryService = new \Google_Service_Directory($client);
    $this->groupSettingsService = new \Google_Service_Groupssettings($client);
  }

  /**
   * Fetch and export memberships using the Google API.
   */
  function fetch($domain) {
    $result = array();

    // Fetch all the groups in this domain
    $opt = array('domain' => $domain);
    $data = $this->directoryService->groups->listGroups($opt);
    $groups = $data->getGroups();

    // Iterate over the groups, and fill in info about each.
    foreach ($groups as $groupInfo) {
      $email = $groupInfo->getEmail();
      $emailParts = explode('@', $email);
      $addressParts = explode('-', $emailParts[0]);
      $office = array_pop($addressParts);
      $branch = implode('-', $addressParts);
      $members = array();
      $membersData = $this->directoryService->members->listMembers($email);
      $membersList = $membersData->getMembers();
      foreach ($membersList as $memberInfo) {
        $members[] = $memberInfo->getEmail();
      }
      $properties['group-name'] = $groupInfo->getName();
      $properties['group-id'] = $groupInfo->getId();
      $results[$branch]['lists'][$office] = array(
        'members' => $members,
        'properties' => $properties,
      );
    }

    return $results;
  }

  function insertBranch($branch) {
    // no-op; we create groups for offices in a branch, but presently
    // we have no Google object that we create for branches.
  }

  function deleteBranch($branch) {
    // no-op; @see insertBranch.
  }

  function verifyBranch($branch) {
    // no-op; @see insertBranch.
    return TRUE;
  }

  function insertMember($branch, $officename, $group_id, $memberEmailAddress) {
    $member = new \Google_Service_Directory_Member(array(
                            'email' => $memberEmailAddress,
                            'role'  => 'MEMBER',
                            'type'  => 'USER'));

    $req = $this->directoryService->members->insert($group_id, $member);
    $this->batch->add($req);
  }

  function removeMember($branch, $officename, $group_id, $memberEmailAddress) {
    $req = $this->directoryService->members->delete($group_id, $memberEmailAddress);
    $this->batch->add($req);
  }

  // TODO: it is inefficient to verify group memberships one user at a time.
  function verifyMember($branch, $officename, $group_id, $memberEmailAddress) {
    try {
      $data = $this->directoryService->members->listMembers($group_id);
    }
    catch (Exception $e) {
      return FALSE;
    }
    // TODO: check $memberEmailAddress against returned data
    return TRUE;
  }

  function insertOffice($branch, $officename, $properties) {
    $group_email = $properties['group-email'];
    $group_name = $properties['group-name'];

    $newgroup = new \Google_Service_Directory_Group(array(
      'email' => "$group_email",
      'name' => "$group_name",
    ));

    $req = $this->directoryService->groups->insert($newgroup);
    $this->batch->add($req);
  }

  function configureOffice($branch, $officename, $properties) {
    $groupId = $properties['group-id'];
    $settingData = new \Google_Service_Groupssettings_Groups();

    // TODO: pull settings from properties

    // INVITED_CAN_JOIN or CAN_REQUEST_TO_JOIN, etc.
    $settingData->setWhoCanJoin("INVITED_CAN_JOIN");
    // ALL_MANAGERS_CAN_POST, ALL_IN_DOMAIN_CAN_POST,
    // ANYONE_CAN_POST, etc.
    $settingData->setWhoCanPostMessage("ANYONE_CAN_POST");

    $req = $this->groupSettingsService->groups->patch($groupId, $settingData);
    $this->batch->add($req);

    if (isset($properties['alternate-addresses'])) {
      foreach ($properties['alternate-addresses'] as $alternate_address) {
        $newalias = new \Google_Service_Directory_Alias(array(
          'alias' => $alternate_address,
        ));
        $req = $this->directoryService->groups_aliases->insert($groupId, $newalias);
        $this->batch->add($req);
      }
    }
  }

  function deleteOffice($branch, $officename, $properties) {
    $groupId = $properties['group-id'];
    $req = $this->directoryService->groups->delete($groupId);
    $this->batch->add($req);
  }

  function verifyOffice($branch, $officename, $properties) {
    $groupId = $properties['group-id'];
    try {
      $data = $this->directoryService->groups->get($groupId);
    }
    catch (Exception $e) {
      return FALSE;
    }
    return TRUE;
  }

  function verifyOfficeConfiguration($branch, $officename, $properties) {
    $groupId = $properties['group-id'];
    $opt_params = array(
      'alt' => "json"
    );
    try {
      $settingData = $this->groupSettingsService->groups->get($groupId, $opt_params);
    }
    catch (Exception $e) {
      return FALSE;
    }

    if (($settingData->getWhoCanJoin() != "INVITED_CAN_JOIN") ||
        ($settingData->getWhoCanPostMessage() != "ANYONE_CAN_POST")) {
      return FALSE;
    }

    return TRUE;
  }

  function insertGroupAlternateAddress($branch, $officename, $group_id, $alternateAddress) {
    $newAlternateAddress = new \Google_Service_Directory_Alias(array(
      'alias' => $alternateAddress,
      ));
    $req = $this->directoryService->groups_aliases->insert($group_id, $newAlternateAddress);
    $this->batch->add($req);
  }

  function removeGroupAlternateAddress($branch, $officename, $group_id, $alternateAddress) {
    // n.b. inserting an alias also adds a non-editable alias, but deleting
    // an alias does not delete its non-editable counterpart.
    $req = $this->directoryService->groups_aliases->delete($group_id, $alternateAddress);
    $this->batch->add($req);
  }

  // TODO: it is inefficient to verify alternate addresses one at a time.
  function verifyGroupAlternateAddress($branch, $officename, $group_id, $alternateAddress) {
    try {
      $aliasData = $this->directoryService->groups_aliases->get($groupId);
    }
    catch (Exception $e) {
      return FALSE;
    }
    // TODO: check $aliasData against $alternateAddress
    return TRUE;
  }

  function begin() {
    $this->client->setUseBatch(TRUE);
  }

  function complete($execute = NULL) {
    $result = array();
    $this->client->setUseBatch(FALSE);
    if (!isset($execute)) {
      $execute = $this->autoExecute;
    }
    if ($execute) {
      $result = $this->execute();
    }
    return $result;
  }

  function execute() {
    return $this->batch->execute();
  }
}
