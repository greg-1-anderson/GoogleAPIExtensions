<?php

namespace Westkingdom\GoogleAPIExtensions;

class Journal implements GroupsController {
  protected $ctrl;

  function __construct($ctrl) {
    $this->ctrl = $ctrl;
  }

  function begin() {
    return $this->ctrl->begin();
  }

  function insertBranch($branch) {
    return $this->ctrl->insertBranch($branch);
  }

  function deleteBranch($branch) {
    return $this->ctrl->deleteBranch($branch);
  }

  function insertMember($branch, $officename, $memberEmailAddress) {
    return $this->ctrl->insertMember($branch, $officename, $memberEmailAddress);
  }

  function removeMember($branch, $officename, $memberEmailAddress) {
    return $this->ctrl->removeMember($branch, $officename, $memberEmailAddress);
  }

  function insertGroupAlternateAddress($branch, $officename, $alternateAddress) {
    return $this->ctrl->insertGroupAlternateAddress($branch, $officename, $alternateAddress);
  }

  function removeGroupAlternateAddress($branch, $officename, $alternateAddress) {
    return $this->ctrl->removeGroupAlternateAddress($branch, $officename, $alternateAddress);
  }

  function insertOffice($branch, $officename, $properties) {
    return $this->ctrl->insertOffice($branch, $officename, $properties);
  }

  function deleteOffice($branch, $officename, $properties) {
    return $this->ctrl->deleteOffice($branch, $officename, $properties);
  }

  function complete() {
    return $this->ctrl->complete();
  }
}
