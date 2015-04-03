<?php

namespace Westkingdom\GoogleAPIExtensions\Internal;

class Operation {
  protected $runFunction;
  protected $runParameters;
  protected $verifyFunction;
  protected $verifyParameters;
  protected $operationId;
  protected $operationSequence;
  protected $executed;
  protected $batchErrors;

  function __construct($runFn, $runParams, $verifyFn = NULL, $verifyParams = NULL) {
    $this->runFunction = $runFn;
    $this->runParameters = $runParams;
    $this->verifyFunction = $verifyFn;
    $this->verifyParameters = $verifyParams;

    array_unshift($this->runParameters, $this);
    if ($verifyParams) {
      array_unshift($this->verifyParameters, $this);
    }

    $this->operationId = mt_rand();
    $this->operationSequence = 0;
    $this->executed = FALSE;
    $this->batchErrors = array();
  }

  function equals(Operation $other) {
    // Must have the same run function to be the same
    if ($this->getRunFunctionName() != $other->getRunFunctionName()) {
      return FALSE;
    }
    // Compare the parameters too
    $thisParameters = $this->getRunFunctionParameters();
    $otherParameters = $other->getRunFunctionParameters();
    // First parameter is always the op, so ignore it.
    array_shift($thisParameters);
    array_shift($otherParameters);
    foreach ($thisParameters as $param) {
      $otherParam = array_shift($otherParameters);
      if ($param != $otherParam) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Return a seqence number consisting of the operation id
   * for this operation followed by ":" and a sequence number.
   * Example: 414530998:1
   */
  function nextSequenceNumber() {
    $this->operationSequence++;
    return $this->operationId . ":" . $this->operationSequence;
  }

  /**
   * Check to see if a batch response id matches this operation.
   * Batch response ids are made by appending the operation sequence
   * number to "response-", so we strip off everything up to the
   * first "-", and everything after the ":", and see if the remainder
   * matches our operation id.
   */
  function compareId($checkId) {
    $checkId = preg_replace('/.*-|:.*/','', $checkId);
    return $checkId == $this->operationId;
  }

  function recordExecutionResult($batchResult) {
    // The Google API returns a 'null' record when everything is okay
    if (empty($batchResult)) {
      $this->executed = TRUE;
      // If simulated, don't run the verify function -- just claim the operation worked.
      if ($batchResult === FALSE) {
        $this->verifyFunction = NULL;
      }
    }
    else {
      $this->batchErrors[] = $batchResult;
    }
  }

  function needsVerification() {
    return $this->executed;
  }

  function getRunFunction() {
    return $runFunction;
  }

  function getRunFunctionName() {
    if (is_string($this->runFunction)) {
      return $this->runFunction;
    }
    else {
      return $this->runFunction[1];
    }
  }

  function getRunFunctionParameters() {
    return $this->runParameters;
  }

  function getVerifyFunctionParameters() {
    return $this->verifyParameters ?: $this->runParameters;
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
