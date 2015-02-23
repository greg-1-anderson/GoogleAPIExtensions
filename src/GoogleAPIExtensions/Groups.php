<?php

namespace GoogleAPIExtensions;

class Groups {
  protected $existingState = array();

  function __construct($state) {
    $this->existingState = $state;
  }

  function update($auth, $memberships) {
    var_export($this->existingState);
    var_export($memberships);
  }

}
