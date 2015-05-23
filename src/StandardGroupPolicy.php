<?php

namespace Westkingdom\GoogleAPIExtensions;

/**
 * Use this groups controller with Westkingdom\GoogleAPIExtensions\Groups
 * to update groups and group memberships directly in Google Apps.
 *
 * The Standard Group Policy has some reasonable defaults that may
 * be overridden by the caller using its simple templating system.
 * If the templating system is not flexible enough, extend StandardGroupPolicy
 * and replace methods as needed.
 *
 * When a group policy is called, it is provided with a set of
 * properties, which are stored in a simple associative array
 * (key => value).  If any returned value contains substitution
 * expressions, these will be replaced with the value of the
 * properties they name.
 *
 * Substitutions can be expressed in two forms:
 *
 * $(name) - returns the value of the property 'name'
 * ${name} - as above, but value passed through ucfirst()
 *
 * Examples:
 *
 *       'group-name'  => '${branch} ${office}',
 *       'group-email' => '$(branch)-$(office)@$(domain)',
 *
 * These can be overridden using the $defaults parameter
 * to the StandardGroupPolicy constructor.
 */
class StandardGroupPolicy implements GroupPolicy {
  protected $defaults;
  protected $oldPolicy;

  /**
   * @param $domain The base domain for all groups
   * @param $defaults Default property values
   */
  function __construct($domain, $defaults = array()) {
    $this->defaults = $defaults + array(
      'domain' => $domain,
      'subdomains' => '',
      'group-name' => '${branch} ${office}',
      'group-email' => '$(simplified-branch)-$(simplified-office)@$(domain)',
      'top-level-group' => preg_replace('/\.[a-z]*$/', '', $domain),
      'top-level-group-email' => '$(simplified-office)@$(domain)',
      'subdomain-group-email' => '$(simplified-office)@$(simplified-branch).$(domain)',
      'aggragate-all-name' => 'All ${office-plural}',
      'aggragate-all-key' => 'all-$(simplified-office-plural)',
      'aggragate-all-email' => 'all-$(simplified-office-plural)@$(domain)',
      'aggragate-branch-officers-name' => '${branch} Officers',
      'aggragate-branch-officers-key' => '$(simplified-branch)-officers',
      'aggragate-branch-officers-email' => '$(simplified-branch)-officers@$(domain)',
      'subdomain-aggragate-branch-officers-email' => 'officers@$(simplified-branch).$(domain)',
      'tld-aggragate-branch-officers-email' => 'officers@$$(domain)',
      'aggragate-all-subgroup-name' => 'All ${subgroup} ${office-plural}',
      'aggragate-all-subgroup-key' => '$(subgroup)-all-$(simplified-office-plural)',
      'aggragate-all-subgroup-email' => '$(simplified-subgroup)-all-$(simplified-office-plural)@$(domain)',
      'subdomain-aggragate-all-subgroup-email' => 'all-$(simplified-office-plural)@$(simplified-subgroup).$(domain)',
    );
  }

  /**
   * The standard policy for the group id is to
   * use the primary group email address as its id.
   */
  function getGroupId($branch, $officename, $properties = array()) {
    $id = $this->getProperty('group-id', $this->defaultGroupProperties($branch, $officename, $properties));
    if ($id) {
      return $id;
    }
    return $this->getProperty('group-email', $this->defaultGroupProperties($branch, $officename, $properties));
  }

  /**
   * The standard policy for the primary group
   * email address is to use branch-office@domain.
   */
  function getGroupEmail($branch, $officename, $properties = array()) {
    return $this->getProperty('group-email', $this->defaultGroupProperties($branch, $officename, $properties));
  }

  function getGroupDefaultAlternateAddresses($branch, $officename, $properties = array()) {
    $groupProperties = $this->defaultGroupProperties($branch, $officename, $properties);
    $alternate_addresses = array();
    $top_level_group = $this->getProperty('top-level-group', $groupProperties);
    if ($branch == $top_level_group) {
      $alternate_addresses[] = $this->getProperty('top-level-group-email', $groupProperties);
    }
    else {
      // TODO: if we had a list of valid subdomains, e.g. sub.domain.org, then
      // we could also test to see if $branch == sub, then create an
      // alias 'office@sub.domain.org' for the standard 'sub-office@domain.org'.
      $branchIsSubdomain = $this->isSubdomain($branch, $groupProperties);
      if ($branchIsSubdomain) {
        $alternate_addresses[] = $this->getProperty('subdomain-group-email', $groupProperties);
      }
      // TODO: if we had information about the heirarchy of branches, then
      // we could make a subdomain-group-email alternate address
      // 'branch-office@sub.domain.org', where 'sub' is the nearest parent
      // branch that has a valid subdomain 'sub.domain.org'.
    }
    return $alternate_addresses;
  }

  function defaultGroupProperties($branch, $officename, $properties = array()) {
    $result = $properties + array(
      'branch' => $branch,
      'office' => $officename,
      'office-plural' => $this->plural($officename),
    );

    $result += array(
      'simplified-branch' => $this->simplifyBranchName($result['branch']),
      'simplified-office' => $this->simplifyOfficeName($result['office']),
    );

    $result += array(
      'simplified-office-plural' => $this->simplifyOfficeName($this->plural($result['simplified-office'])),
    );

    return $result;
  }

  function isSubdomain($branch, $properties = array()) {
    $subdomains = $this->getProperty('subdomains', $properties);
    if (empty($subdomains)) {
      return FALSE;
    }
    if ($subdomains == 'all') {
      return TRUE;
    }
    $negate = ($subdomains[0] == '!');
    if ($negate) {
      $subdomains = substr($subdomains, 1);
    }
    $subdomains = explode(',', $subdomains);
    $result = in_array($branch, $subdomains);
    return $negate ? !$result : $result;
  }

  // TODO:  is there somewhere we could store the plural for office-plural?
  function plural($noun) {
    // For the offices we currently have, it works well to just add
    // an "s" if there isn't already an "s" at the end of the name.
    if (substr($noun, -1) == "s") {
      return $noun;
    }
    else {
      return $noun . "s";
    }
  }

  /**
   * The standard policy for the group name is to use
   * the name provided in the properties; if a name is
   * not provided, then "Branch Office" is used instead.
   */
  function getGroupName($branch, $officename, $properties = array()) {
    return $this->getProperty('group-name', $this->defaultGroupProperties($branch, $officename, $properties));
  }

  /**
   * Return the domain name associated with these Google groups.
   */
  function getDomain() {
    return $this->defaults['domain'];
  }

  /**
   * Determine the aggregate groups that this office is a member of.
   *
   * @returns array 'name' => array of properties
   *   Each item returned should be the name of an aggregate group that this
   *   office is a member of.  The properties of the resulting item should
   *   include the group-id, the group-name, and the group-email.
   */
  function getAggregatedGroups($branch, $officename, $properties = array(), $parentage = array()) {
    $office_properties = $this->defaultGroupProperties($branch, $officename, $properties);
    $top_level_group = $this->getProperty('top-level-group', $office_properties);
    $result = array();

    // Put in an entry for 'all-$officename@domain'
    $allName = $this->getProperty('aggragate-all-name', $office_properties);
    $allEmail = $this->getProperty('aggragate-all-email', $office_properties);
    $allKey = $this->getProperty('aggragate-all-key', $office_properties);
    $result[$allKey] = array('group-id' => $allEmail, 'group-name' => $allName, 'group-email' => $allEmail);

    // Also put in an entry for '$branch-officers@domain'
    $officersName = $this->getProperty('aggragate-branch-officers-name', $office_properties);
    $officersEmail = $this->getProperty('aggragate-branch-officers-email', $office_properties);
    $officersKey = $this->getProperty('aggragate-branch-officers-key', $office_properties);
    $result[$officersKey] = array('group-id' => $officersEmail, 'group-name' => $officersName, 'group-email' => $officersEmail);
    if ($top_level_group == $branch) {
      $result[$officersKey]['alternate-addresses'][] = $this->getProperty('tld-aggragate-branch-officers-email', $office_properties);
    }
    else {
      $result[$officersKey]['alternate-addresses'][] = $this->getProperty('subdomain-aggragate-branch-officers-email', $office_properties);
    }

    // If this is not a top-level group, then put in an entry
    // for 'all-$parentage-$officename@domain' for each item
    // in this subgroup's parentage -- including the subgroup itself.
    if ($top_level_group != $branch) {
      $parentage[] = $branch;
      foreach ($parentage as $subgroup) {
        $office_properties['subgroup'] = $subgroup;
        $office_properties['simplified-subgroup'] = $this->simplify($subgroup);

        $allName = $this->getProperty('aggragate-all-subgroup-name', $office_properties);
        $allEmail = $this->getProperty('aggragate-all-subgroup-email', $office_properties);
        $allKey = $this->getProperty('aggragate-all-subgroup-key', $office_properties);
        $result[$allKey] = array('group-id' => $allEmail, 'group-name' => $allName, 'group-email' => $allEmail);
        $subgroupIsSubdomain = $this->isSubdomain($subgroup, $office_properties);
        if ($subgroupIsSubdomain) {
          $allSubdomainEmail = $this->getProperty('subdomain-aggragate-all-subgroup-email', $office_properties);
          $result[$allKey]['properties']['alternate-addresses'][] = $allSubdomainEmail;
        }
      }
    }
    return $result;
  }

  /**
   * Normalize an email address.
   *
   * Addresses without a domain are assumed to be in the
   * primary domain.
   *
   * We also convert all addresses to lowercase.
   */
  function normalizeEmail($email) {
    if (strstr($email, "@") === FALSE) {
      $email .= "@" . $this->getProperty('domain');
    }
    return strtolower($email);
  }

  /**
   * Simplify a branch name, e.g. for inclusion in an email address
   */
  function simplifyBranchName($branch) {
    return $this->simplify($branch);
  }

  /**
   * Simplify an office name, e.g. for inclusion in an email address
   */
  function simplifyOfficeName($office) {
    return $this->simplify($office);
  }

  function simplify($name) {
    return preg_replace('/[^a-z0-9]/', '', strtolower($name));
  }

  function availableDefaults() {
    return array_keys($this->defaults);
  }

  function getProperty($propertyId, $properties = array()) {
    $value = $this->getPropertyValue($propertyId, $properties);
    return $this->applyTemplate($value, $properties);
  }

  protected function getPropertyValue($propertyId, $properties = array()) {
    if (isset($properties[$propertyId])) {
      return $properties[$propertyId];
    }
    elseif (isset($this->defaults[$propertyId])) {
      return $this->defaults[$propertyId];
    }
    return NULL;
  }

  protected function applyTemplate($template, $properties) {
    if (!$template) {
      return NULL;
    }
    $result = $template;
    preg_match_all('/\$([{(])([a-z-]*)[})]/', $template, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $replacementPropertyId = $match[2];
      $uppercase = $match[1] == '{';
      $replacement = $this->getPropertyValue($replacementPropertyId, $properties);
      if (!isset($replacement)) {
        $replacement = '';
      }
      if ($uppercase) {
        $replacement = ucfirst($replacement);
      }
      $result = str_replace($match[0], $replacement, $result);
    }
    return $result;
  }

  function generateParentage($memberships) {
    $top_level_group = $this->getProperty('top-level-group');
    if (array_key_exists($top_level_group, $memberships) && array_key_exists('subgroups', $memberships[$top_level_group])) {
      foreach ($memberships[$top_level_group]['subgroups'] as $subgroup) {
        $this->generateParentageForBranch($memberships, $subgroup);
      }
    }
    return $memberships;
  }

  protected function generateParentageForBranch(&$memberships, $branch, $parentage = array()) {
    if (array_key_exists('subgroups', $memberships[$branch])) {
      array_unshift($parentage, $branch);
      foreach ($memberships[$branch]['subgroups'] as $subgroup) {
        if (array_key_exists($subgroup, $memberships)) {
          $memberships[$subgroup]['parentage'] = $parentage;
          $this->generateParentageForBranch($memberships, $subgroup, $parentage);
        }
      }
    }
  }


  /**
   * Generate aggragated groups
   */
  function generateAggregatedGroups($memberships) {
    $aggregatedGroups = array();
    // Generate the aggregated group information
    foreach ($memberships as $branch => $branchinfo) {
      if ((ctype_alpha($branch[0])) && array_key_exists('lists', $branchinfo)) {
        foreach ($branchinfo['lists'] as $office => $officeData) {
          // Get the list of aggragated lists for this group.
          $parentage = isset($branchinfo['parentage']) ? $branchinfo['parentage'] : array();
          $aggragatedLists = $this->getAggregatedGroups($branch, $office, $officeData['properties'], $parentage);
          foreach ($aggragatedLists as $aggregateName => $aggregateGroupInfo) {
            $this->addAggregateGroupMember($aggregatedGroups, $branch, $office, $aggregateName, $aggregateGroupInfo);
          }
        }
      }
    }
    // Go back and remove any aggregated group that has only one member
    foreach ($aggregatedGroups as $group => $data) {
      if (!isset($data['members']) || (count($data['members']) <= 1)) {
        unset($aggregatedGroups[$group]);
      }
    }

    return $aggregatedGroups;
  }

  /**
   * Add a member to one aggregate group
   */
  protected function addAggregateGroupMember(&$aggregatedGroups, $branch, $office, $aggregatedGroupName, $aggregatedGroupProperties) {
    $emailAddress = $this->getGroupEmail($branch, $office);
    if (!isset($aggregatedGroups[$aggregatedGroupName])) {
      $aggregatedGroups[$aggregatedGroupName] = array(
        'properties' => $aggregatedGroupProperties,
      );
    }
    $aggregatedGroups[$aggregatedGroupName]['members'][] = $emailAddress;
  }

  function normalize($state) {
    $result = array();
    foreach ($state as $branch => $branchInfo) {
      $result[$branch] = $this->normalizeLists($branch, $branchInfo);
      if (array_key_exists('subgroups', $branchInfo)) {
        $result[$branch]['subgroups'] = $branchInfo['subgroups'];
      }
    }
    return $result;
  }

  function normalizeLists($branch, $listsAndAliases) {
    // First, normalize the offices data
    $offices = array('lists' => array());
    if (array_key_exists('lists', $listsAndAliases)) {
      $offices['lists'] = $this->normalizeGroupsData($branch, $listsAndAliases['lists']);
    }
    return $offices;
  }

  /**
   * Convert the alias data into a format that can be merged
   * in with the lists.
   */
  function normalizeGroupsData($branch, $aliasGroups, $default = array()) {
    $result = array();
    foreach ($aliasGroups as $office => $data) {
      $data = $this->normalizeMembershipData($data);
      $data['properties'] += $default;
      // If the user supplied an email address for the group that is different than
      // the generated email address, then use the generated address instead
      // here.  We'll convert the supplied address into an alternate address.
      $suppliedGroupEmail = $this->getGroupEmail($branch, $office, $data['properties']);
      unset($data['properties']['group-email']);
      $standardGroupEmail = $this->getGroupEmail($branch, $office, $data['properties']);
      $data['properties'] += array(
        'group-email' => $standardGroupEmail,
      );
      $data['properties'] += array(
        'group-id' => $this->getGroupId($branch, $office, $data['properties']),
      );
      $data['properties'] += array(
        'group-name' => $this->getGroupName($branch, $office, $data['properties']),
      );
      // Get the alternate addresses for this group; add in the supplied
      // group address, if it was different than the generated address.
      $alternate_addresses = $this->getGroupDefaultAlternateAddresses($branch, $office, $data['properties']);
      if ($standardGroupEmail != $suppliedGroupEmail) {
        $alternate_addresses[] = $suppliedGroupEmail;
      }
      if (!empty($alternate_addresses)) {
        if (!isset($data['properties']['alternate-addresses'])) {
          $data['properties']['alternate-addresses'] = array();
        }
        $data['properties']['alternate-addresses'] = array_unique(array_map(array($this, 'normalizeEmail'), array_merge((array)$data['properties']['alternate-addresses'], $alternate_addresses)));
        sort($data['properties']['alternate-addresses']);
      }
      $result[$office] = $data;
    }
    return $result;
  }

  /**
   * Take the membership data and normalize it to always
   * be an associative array with the membership list in
   * an element named 'members'.
   *
   * An array without a 'members' element is presumed to be
   * a list of user email addresses without any additional
   * metadata for the group.
   *
   * A simple string is treated like an array of one element.
   */
  function normalizeMembershipData($data) {
    if (is_string($data)) {
      return $this->normalizeMembershipArrayData(array($data));
    }
    else {
      return $this->normalizeMembershipArrayData($data);
    }
  }

  /**
   * If the array is not associative, then convert it to
   * an array with just a 'members' element containing all
   * of the original data contents.
   */
  function normalizeMembershipArrayData($data) {
    if (array_key_exists('members', $data)) {
      $result = $data + array('properties' => array());
    }
    else {
      // TODO: confirm that all of the keys of $data are numeric, and all the values are strings
      $result = array('members' => $data, 'properties' => array());
    }
    return $this->normalizeMemberAddresses($result);
  }

  /**
   * Pass all of the email addresses in $data['members']
   * through the 'normalizeEmail()' function.
   */
  function normalizeMemberAddresses($data) {
    $normalizedAddresses = array();
    foreach ($data['members'] as $address) {
      $normalizedAddresses[] = $this->normalizeEmail($address);
    }
    $data['members'] = $normalizedAddresses;

    return $data;
  }
}
