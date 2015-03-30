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
      'group-name' => '${branch} ${office}',
      'group-email' => '$(simplified-branch)-$(simplified-office)@$(domain)',
      'top-level-group' => preg_replace('/\.[a-z]*$/', '', $domain),
      'top-level-group-email' => '$(office)@$(domain)',
    );
  }

  /**
   * The standard policy for the group id is to
   * use the primary group email address as its id.
   */
  function getGroupId($branch, $officename) {
    return $this->getGroupEmail($branch, $officename);
  }

  /**
   * The standard policy for the primary group
   * email address is to use branch-office@domain.
   */
  function getGroupEmail($branch, $officename) {
    return $this->getProperty('group-email', $this->defaultGroupProperties($branch, $officename));
  }

  function defaultGroupProperties($branch, $officename) {
    return array(
      'branch' => $branch,
      'office' => $officename,
      'simplified-branch' => $this->simplifyBranchName($branch),
      'simplified-office' => $this->simplifyOfficeName($officename),
    );
  }

  /**
   * The standard policy for the group name is to use
   * the name provided in the properties; if a name is
   * not provided, then "Branch Office" is used instead.
   */
  function getGroupName($branch, $officename, $properties = array()) {
    return $this->getProperty('group-name', $this->defaultGroupProperties($branch, $officename));
  }

  /**
   * Return the domain name associated with these Google groups.
   */
  function getDomain() {
    return $this->defaults['domain'];
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
      $result[$branch] = $this->normalizeListsAndAliases($branch, $listsAndAliases);
    }
    return $result;
  }

  function normalizeListsAndAliases($branch, $listsAndAliases) {
    // First, normalize the offices data
    $offices = array('lists' => array());
    if (array_key_exists('lists', $listsAndAliases)) {
      $offices['lists'] = $this->normalizeGroupsData($branch, $listsAndAliases['lists']);
    }
    if (array_key_exists('aliases', $listsAndAliases)) {
      $default = array(
        'forward-only' => TRUE,
      );
      $offices['lists'] += $this->normalizeGroupsData($branch, $listsAndAliases['aliases'], $default);
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
      $data['properties'] += array(
        'group-email' => $this->getGroupEmail($branch, $office),
        'group-id' => $this->getGroupId($branch, $office),
      );
      $propertiesForGroupName = $data['properties'] + $this->defaultGroupProperties($branch, $office);
      $data['properties'] += array(
        'group-name' => $this->getGroupName($branch, $office, $propertiesForGroupName),
      );
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
