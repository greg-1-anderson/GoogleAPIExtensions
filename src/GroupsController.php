<?php

namespace Westkingdom\GoogleAPIExtensions;

interface GroupsController {
  function begin();
  function insertBranch($branch);
  function deleteBranch($branch);
  function verifyBranch($branch);
  function insertMember($branch, $officename, $group_id, $memberEmailAddress);
  function removeMember($branch, $officename, $group_id, $memberEmailAddress);
  function verifyMember($branch, $officename, $group_id, $memberEmailAddress);
  function insertGroupAlternateAddress($branch, $officename, $group_id, $alternateAddress);
  function removeGroupAlternateAddress($branch, $officename, $group_id, $alternateAddress);
  function verifyGroupAlternateAddress($branch, $officename, $group_id, $alternateAddress);
  function insertOffice($branch, $officename, $properties);
  function configureOffice($branch, $officename, $properties);
  function deleteOffice($branch, $officename, $properties);
  function verifyOffice($branch, $officename, $properties);
  function verifyOfficeConfiguration($branch, $officename, $properties);
  function complete();
}
