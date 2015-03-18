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
  protected $groupPolicy;
  protected $autoExecute = FALSE;

  /**
   * @param $client Google Apps API client object
   * @param $policy Policy object that controls group names and behaviors
   * @param $batch Google Apps batch object. Optional; one will be created
   * if none provided.
   */
  function __construct($client, $policy, $batch = NULL) {
    $this->client = $client;
    $this->batch = $batch;
    if (!isset($batch)) {
      $client->setUseBatch(true);
      $this->batch = new \Google_Http_Batch($client);
      $this->autoExecute = TRUE;
    }
    $this->directoryService = new \Google_Service_Directory($client);
    $this->groupSettingsService = new \Google_Service_Groupssettings($client);

    $this->groupPolicy = $policy;
  }

  function insertBranch($branch) {
    // no-op; we create groups for offices in a group, but presently
    // we have no Google object that we create for branches.
  }

  function deleteBranch($branch) {
    // no-op; @see insertBranch.
  }

  function insertMember($branch, $officename, $memberEmailAddress) {
    $group_id = $this->groupPolicy->getGroupId($branch, $officename);

    $member = new \Google_Service_Directory_Member(array(
                            'email' => $memberEmailAddress,
                            'role'  => 'MEMBER',
                            'type'  => 'USER'));

    $req = $this->directoryService->members->insert($group_id, $member);
    $this->batch->add($req);
  }

  function removeMember($branch, $officename, $memberEmailAddress) {
    $group_id = $this->groupPolicy->getGroupId($branch, $officename);

    $req = $this->directoryService->members->delete($group_id, $memberEmailAddress);
    $this->batch->add($req);
  }

  function insertOffice($branch, $officename, $properties) {
    $group_email = $this->groupPolicy->getGroupEmail($branch, $officename);
    $group_name = $this->groupPolicy->getGroupName($branch, $officename, $properties);

    $newgroup = new \Google_Service_Directory_Group(array(
      'email' => "$group_email",
      'name' => "$group_name",
    ));

    $req = $this->directoryService->groups->insert($newgroup);
    $this->batch->add($req);

    $settingData = new \Google_Service_Groupssettings_Groups();

    // INVITED_CAN_JOIN or CAN_REQUEST_TO_JOIN, etc.
    $settingData->setWhoCanJoin("INVITED_CAN_JOIN");
    // ALL_MANAGERS_CAN_POST, ALL_IN_DOMAIN_CAN_POST, works
    // ANYONE_CAN_POST returns 'permission denied'.
    $settingData->setWhoCanPostMessage("ALL_IN_DOMAIN_CAN_POST");

    $req = $this->groupSettingsService->groups->patch($group_email, $settingData);
    $this->batch->add($req);

    $group_id = $this->groupPolicy->getGroupId($branch, $officename);
    $this->groupSettingsService->groups->patch($group_id, $settingData);

    if (isset($properties['alternate-addresses'])) {
      foreach ($properties['alternate-addresses'] as $alternate_address) {
        $newalias = new \Google_Service_Directory_Alias(array(
          'alias' => $alternate_address,
        ));
        $req = $this->directoryService->groups_aliases->insert($group_id, $newalias);
        $this->batch->add($req);
      }
    }
  }

  function deleteOffice($branch, $officename, $properties) {
    $group_id = $this->groupPolicy->getGroupId($branch, $officename);
    $req = $this->directoryService->groups->delete($group_id);
    $this->batch->add($req);
  }

  function begin() {
  }

  function complete() {
    if ($this->autoExecute) {
      $this->execute();
    }
  }

  function execute() {
    return $this->batch->execute();
  }
}
