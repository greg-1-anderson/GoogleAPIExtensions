<?php

namespace Westkingdom\GoogleAPIExtensions;

interface GroupsController {
  function insertBranch($branch);
  function deleteBranch($branch);
  function insertMember($branch, $officename, $memberEmailAddress);
  function removeMember($branch, $officename, $memberEmailAddress);
  function insertOffice($branch, $officename, $properties);
  function deleteOffice($branch, $officename, $properties);
}
