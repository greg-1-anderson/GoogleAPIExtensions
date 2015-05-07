<?php

use Westkingdom\GoogleAPIExtensions\GroupsManager;
use Westkingdom\GoogleAPIExtensions\StandardGroupPolicy;
use Westkingdom\GoogleAPIExtensions\Internal\Journal;

use Prophecy\PhpUnit\ProphecyTestCase;
use Prophecy\Argument\Token\AnyValueToken;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Dumper;

class PolicyTestCase extends ProphecyTestCase {

  protected $initialState = array();
  protected $policy;

  public function setUp() {
    parent::setup();

    $groupData = "
west:
  lists:
    webminister:
      members:
        - minister@sca.org
        - deputy@sca.org
      properties:
        group-name: West Kingdom Web Minister";
    $this->initialState = Yaml::parse(trim($groupData));

    $properties = array(
      'top-level-group' => 'north',
      'subdomains' => 'fogs,geese,wolves,lightwoods',
    );
    $this->policy = new StandardGroupPolicy('testdomain.org', $properties);
  }

  public function testNormalize() {
    $data = Yaml::parse("
north:
  lists:
    president:
      - bill@testdomain.org
    vice-president:
      members:
        - walter@testdomain.org
      properties:
        alternate-addresses:
          - vice@testdomain.org
    secretary:
      - george@testdomain.org
fogs:
  lists:
    president:
      - frank@testdomain.org");

    $normalized = $this->policy->normalize($data);

    $expected = "
north:
  lists:
    president:
      members:
        - bill@testdomain.org
      properties:
        group-email: north-president@testdomain.org
        group-id: north-president@testdomain.org
        group-name: 'North President'
        alternate-addresses:
          - president@testdomain.org
    vice-president:
      members:
        - walter@testdomain.org
      properties:
        alternate-addresses:
          - vice@testdomain.org
          - vicepresident@testdomain.org
        group-email: north-vicepresident@testdomain.org
        group-id: north-vicepresident@testdomain.org
        group-name: 'North Vice-president'
    secretary:
      members:
        - george@testdomain.org
      properties:
        group-email: north-secretary@testdomain.org
        group-id: north-secretary@testdomain.org
        group-name: 'North Secretary'
        alternate-addresses:
          - secretary@testdomain.org
fogs:
  lists:
    president:
      members:
        - frank@testdomain.org
      properties:
        group-email: fogs-president@testdomain.org
        group-id: fogs-president@testdomain.org
        group-name: 'Fogs President'
        alternate-addresses:
          - president@fogs.testdomain.org";

    $this->assertYamlEquals(trim($expected), $normalized);
  }


  public function testParentage() {
    $data = Yaml::parse("
north:
  subgroups:
    - fogs
    - geese
    - wolves
fogs:
  subgroups:
    - lightwoods
geese:
  subgroups:
    - gustyplains
wolves:
  subgroups:
    - coldholm
lightwoods:
  subgroups:
    - seamountain
gustyplains:
  subgroups: {  }
coldholm:
  subgroups: {  }
seamountain:
  subgroups: {  }");

    $result = $this->policy->generateParentage($data);

    $expected = "
north:
  subgroups:
    - fogs
    - geese
    - wolves
fogs:
  subgroups:
    - lightwoods
geese:
  subgroups:
    - gustyplains
wolves:
  subgroups:
    - coldholm
lightwoods:
  subgroups:
    - seamountain
  parentage:
    - fogs
gustyplains:
  subgroups: {  }
  parentage:
    - geese
coldholm:
  subgroups: {  }
  parentage:
    - wolves
seamountain:
  subgroups: {  }
  parentage:
    - lightwoods
    - fogs";

    $this->assertYamlEquals(trim($expected), $result);
  }

  public function assertYamlEquals($expected, $data) {
    $this->assertEquals($this->arrayToYaml($expected), $this->arrayToYaml($data));
  }

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

}
