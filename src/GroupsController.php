<?php

namespace Westkingdom\GoogleAPIExtensions;

interface GroupsController {
  function begin();
  function insertBranch($branch);
  function deleteBranch($branch);
  function verifyBranch($branch);
  function insertMember($branch, $officename, $memberEmailAddress);
  function removeMember($branch, $officename, $memberEmailAddress);
  function verifyMember($branch, $officename, $memberEmailAddress);
  function insertGroupAlternateAddress($branch, $officename, $alternateAddress);
  function removeGroupAlternateAddress($branch, $officename, $alternateAddress);
  function verifyGroupAlternateAddress($branch, $officename, $alternateAddress);
  function insertOffice($branch, $officename, $properties);
  function deleteOffice($branch, $officename, $properties);
  function verifyOffice($branch, $officename, $properties);
  function complete();
}
