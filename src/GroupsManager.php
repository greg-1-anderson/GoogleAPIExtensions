<?php

namespace Westkingdom\GoogleAPIExtensions;

use Westkingdom\GoogleAPIExtensions\Internal\Journal;

class GroupsManager {
  protected $ctrl;
  protected $updater;
  protected $journal;

  /**
   * @param $ctrl control object that does actual actions
   * @param $state initial state
   */
  function __construct(GroupsController $ctrl, $state, $updater = NULL) {
    $this->ctrl = $ctrl;
    $this->journal = new Journal($ctrl, Utils::normalize($state));

    if (isset($updater)) {
      $this->updater = $updater;
    }
    else {
      $this->updater = new Internal\Updater($this->journal);
    }
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
    $this->updater->update($memberships, $this->journal->getExistingState());
    return $this->journal->getExistingState();
  }

  function execute() {
    $this->journal->execute();
    return $this->journal->getExistingState();
  }
}
