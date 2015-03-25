<?php

namespace Westkingdom\GoogleAPIExtensions\Internal;

class Operation {
  protected $runFunction;
  protected $runParameters;
  protected $verifyFunction;
  protected $verifyParameters;

  function __construct($runFn, $runParams, $verifyFn = NULL, $verifyParams = NULL) {
    $this->runFunction = $runFn;
    $this->runParameters = $runParams;
    $this->verifyFunction = $verifyFn;
    $this->verifyParameters = $verifyParams;
  }

  /**
   * Do the operation
   */
  function run() {
    return call_user_func_array($this->runFunction, $this->runParameters);
  }

  /**
   * Check to see if the operation succeeded
   *
   * @return TRUE if done, any other value if it needs to be retried later.
   */
  function verify() {
    if (!$this->verifyFunction) {
      return TRUE;
    }
    return call_user_func_array($this->verifyFunction, $this->verifyParameters);
  }
}
