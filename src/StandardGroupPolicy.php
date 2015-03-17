<?php

namespace Westkingdom\GoogleAPIExtensions;

/**
 * Use this groups controller with Westkingdom\GoogleAPIExtensions\Groups
 * to update groups and group memberships directly in Google Apps.
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
