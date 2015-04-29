<?php

use Westkingdom\GoogleAPIExtensions\LegacyGroups;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Dumper;

class LegacyGroupsTestCase extends PHPUnit_Framework_TestCase {

  public function arrayToYaml($data) {
    if (is_string($data)) {
      return trim($data);
    }
    else {
      // Convert data to YAML
      $dumper = new Dumper();
      $dumper->setIndentation(2);
      return trim($dumper->dump($data, PHP_INT_MAX));
    }
  }

  public function testLegacyGroups() {
    $testData = "
west:
  lists:
    webminister:
      members:
        - minister@sca.org
        - deputy@sca.org
      properties:
        group-name: 'West Kingdom Web Minister'
        group-email: west-webminister@westkingdom.org
        alternate-addresses:
          - webminister@westkingdom.org";
    $testMemberships = Yaml::parse(trim($testData));

    // We are going to add two members to an existing group,
    // and create a new legacy group with two members.
    $testLegacy ="
west-webminister@westkingdom.org   anotherwebguy@hitec.com
webminister@westkingdom.org   deputywebdude@boilerstrap.com
some-old-group@westkingdom.org   person1@somewhere.org,person2@somewhereelse.org";

    $testMemberships = LegacyGroups::applyLegacyGroups($testMemberships, LegacyGroups::parseLegacyDreamHostGroups($testLegacy));

    $expected = "
west:
  lists:
    webminister:
      members:
        - minister@sca.org
        - deputy@sca.org
        - anotherwebguy@hitec.com
        - deputywebdude@boilerstrap.com
      properties:
        group-name: 'West Kingdom Web Minister'
        group-email: west-webminister@westkingdom.org
        alternate-addresses:
          - webminister@westkingdom.org
_legacy:
  lists:
    some-old-group:
      members:
        - person1@somewhere.org
        - person2@somewhereelse.org
      properties:
        group-name: 'Some old group'
        alternate-addresses:
          - some-old-group@westkingdom.org";

    $this->assertEquals(trim($expected), $this->arrayToYaml($testMemberships));

  }
}
