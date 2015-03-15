namespace GoogleAPIExtensions;

interface GroupsController {
  function insertBranch($branch);
  function deleteBranch($branch);
  function insertMember($branch, $officename, $emailAddress);
  function removeMember($branch, $officename, $emailAddress);
  function insertOffice($branch, $officename, $settings);
  function deleteOffice($branch, $officename, $settings);
}
