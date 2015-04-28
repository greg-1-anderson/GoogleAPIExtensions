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
      'aggrageat-all-key' => 'all-$(simplified-office-plural)',
      'aggrageat-all-email' => 'all-$(simplified-office-plural)@$(domain)',
      'aggragate-branch-officers-name' => '${branch} Officers',
      'aggragate-branch-officers-key' => '$(simplified-branch)-officers',
      'aggragate-branch-officers-email' => '$(simplified-branch)-officers@$(domain)',
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
      $branchIsSubdomain = $this->getProperty('is-subdomain', $groupProperties);
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

    if ($this->isSubdomain($branch, $result)) {
      $result['is-subdomain'] = TRUE;
    }

    return $result;
  }

  function isSubdomain($branch, $properties) {
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
    return $result ^ $negate;
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
   * @returns array 'name' => array of properties
   */
  function getAggregatedGroups($branch, $officename, $properties = array()) {
    $result = array();
    $allName = $this->getProperty('aggragate-all-name', $this->defaultGroupProperties($branch, $officename, $properties));
    $allEmail = $this->getProperty('aggrageat-all-email', $this->defaultGroupProperties($branch, $officename, $properties));
    $allKey = $this->getProperty('aggrageat-all-key', $this->defaultGroupProperties($branch, $officename, $properties));
    $result[$allKey] = array('group-id' => $allEmail, 'group-name' => $allName, 'group-email' => $allEmail);

    $officersName = $this->getProperty('aggragate-branch-officers-name', $this->defaultGroupProperties($branch, $officename, $properties));
    $officersEmail = $this->getProperty('aggragate-branch-officers-email', $this->defaultGroupProperties($branch, $officename, $properties));
    $officersKey = $this->getProperty('aggragate-branch-officers-key', $this->defaultGroupProperties($branch, $officename, $properties));
    $result[$officersKey] = array('group-id' => $officersEmail, 'group-name' => $officersName, 'group-email' => $officersEmail);

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

  function normalize($state) {
    $result = array();
    foreach ($state as $branch => $listsAndAliases) {
      $result[$branch] = $this->normalizeLists($branch, $listsAndAliases);
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
