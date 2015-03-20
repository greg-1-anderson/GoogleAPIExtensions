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

  /**
   * @param $domain The base domain for all groups
   * @param $defaults Default property values
   */
  function __construct($domain, $defaults = array()) {
    $this->defaults = $defaults + array(
      'domain' => $domain,
      'group-name' => '${branch} ${office}',
      'group-email' => '$(branch)-$(office)@$(domain)',
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
    $properties = array(
      'branch' => $branch,
      'office' => $officename,
    );
    return $this->getProperty('group-email', $properties);
  }

  /**
   * The standard policy for the group name is to use
   * the name provided in the properties; if a name is
   * not provided, then "Branch Office" is used instead.
   */
  function getGroupName($branch, $officename, $properties = array()) {
    $properties += array(
      'branch' => $branch,
      'office' => $officename,
    );
    return $this->getProperty('group-name', $properties);
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
    preg_match_all('/\$([{(])([a-z]*)[})]/', $template, $matches, PREG_SET_ORDER);
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
}
