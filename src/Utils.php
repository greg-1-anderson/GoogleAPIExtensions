<?php

namespace Westkingdom\GoogleAPIExtensions;

class Utils {

  static function getAlternateAddresses($branch, $officename, $officeData) {
    $result = array();
    if (isset($officeData['properties']['alternate-addresses'])) {
      $result = (array)$officeData['properties']['alternate-addresses'];
    }
    return $result;
  }

  static function normalize($state) {
    $result = array();
    foreach ($state as $branch => $listsAndAliases) {
      $result[$branch] = Utils::normalizeListsAndAliases($listsAndAliases);
    }
    return $result;
  }

  static function normalizeListsAndAliases($listsAndAliases) {
    // First, normalize the offices data
    $offices = array('lists' => array());
    if (array_key_exists('lists', $listsAndAliases)) {
      $offices['lists'] = Utils::normalizeGroupsData($listsAndAliases['lists']);
    }
    if (array_key_exists('aliases', $listsAndAliases)) {
      $default = array(
        'forward-only' => TRUE,
      );
      $offices['lists'] += Utils::normalizeGroupsData($listsAndAliases['aliases'], $default);
    }
    return $offices;
  }

  /**
   * Convert the alias data into a format that can be merged
   * in with the lists.
   */
  static function normalizeGroupsData($aliasGroups, $default = array()) {
    $result = array();
    foreach ($aliasGroups as $office => $data) {
      $data = Utils::normalizeMembershipData($data);
      $data['properties'] += $default;
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
  static function normalizeMembershipData($data) {
    if (is_string($data)) {
      return Utils::normalizeMembershipArrayData(array($data));
    }
    else {
      return Utils::normalizeMembershipArrayData($data);
    }
  }

  /**
   * If the array is not associative, then convert it to
   * an array with just a 'members' element containing all
   * of the original data contents.
   */
  static function normalizeMembershipArrayData($data) {
    if (array_key_exists('members', $data)) {
      return $data + array('properties' => array());
    }
    else {
      // TODO: confirm that all of the keys of $data are numeric.
      return array('members' => $data, 'properties' => array());
    }
  }
}
