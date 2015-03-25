<?php

namespace Westkingdom\GoogleAPIExtensions;

class GroupsManager {
  protected $existingState = array();
  protected $ctrl;
  protected $updater;

  /**
   * @param $ctrl control object that does actual actions
   * @param $state initial state
   */
  function __construct(GroupsController $ctrl, $state, $updater = NULL) {
    $this->ctrl = $ctrl;
    $this->existingState = Utils::normalize($state);

    if (isset($updater)) {
      $this->updater = $updater;
    }
    else {
      $this->updater = new Updater($ctrl);
    }
  }

  static function createForDomain($applicationName, $domain, $state) {
    $authenticator = ServiceAccountAuthenticator($applicationName);
    $client = $authenticator->authenticate();
    $policy = new StandardGroupPolicy($domain);
    $controller = new GoogleAppsGroupsController($client, $policy);
    $journal = new Journal($controller);
    $groupManager = new GroupsManager($journal, $currentState);
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
    $this->updater->update($memberships, $this->existingState);
    $this->existingState = $memberships;
  }

  function execute() {
    $this->ctrl->execute();
  }
}
