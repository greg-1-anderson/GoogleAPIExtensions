<?php

namespace Westkingdom\GoogleAPIExtensions;

class LegacyGroups {

  static function parseLegacyDreamHostGroups($dreamhostGroups, $blacklist = array()) {
    $legacy = array();
    foreach (explode("\n", $dreamhostGroups) as $group) {
      // Replace all runs of spaces with a single space
      $group = trim(preg_replace('/  */', ' ', $group));
      if (!empty($group) && ($group[0] != '#')) {
        list($emailAddress, $members) = explode(' ', $group, 2);
        if (!in_array($emailAddress, $blacklist)) {
          $members = array_diff(array_map('trim', explode(',', $members)), $blacklist);
          if (!empty($members)) {
            $legacy[$emailAddress] = $members;
          }
        }
      }
    }
    return $legacy;
  }

  static function applyLegacyGroups($memberships, $legacy) {
    foreach ($legacy as $legacyGroup => $members) {
      $matchedExisting = LegacyGroups::applyLegacyGroup($memberships, $legacyGroup, $members);
      if (!$matchedExisting) {
        $legacyOfficename = preg_replace('/@.*/', '', $legacyGroup);
        $memberships['_legacy']['lists'][$legacyOfficename] = array(
          'members' => $members,
          'properties' => array(
            'group-email' => $legacyGroup,
          ),
        );
      }
    }
    return $memberships;
  }

  static protected function applyLegacyGroup(&$memberships, $legacyGroup, $members) {
    foreach ($memberships as $branch => $officesLists) {
      if ($branch[0] != '#') {
        $offices = $officesLists['lists'];
        foreach ($offices as $officename => $officeData) {
          if (LegacyGroups::legacyGroupMatches($legacyGroup, $officename, $officeData)) {
            foreach ($members as $member) {
              $member = trim(strtolower($member));
              if (!in_array($member, $officeData['members'])) {
                $memberships[$branch]['lists'][$officename]['members'][] = $member;
              }
            }
            return TRUE;
          }
        }
      }
    }
    return FALSE;
  }

  static protected function legacyGroupMatches($legacyGroup, $officename, $officeData) {
    if ($legacyGroup == $officeData['properties']['group-email']) {
      return TRUE;
    }
    return isset($officeData['properties']['alternate-addresses']) && in_array($legacyGroup, $officeData['properties']['alternate-addresses']);
  }
}
