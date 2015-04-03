<?php

namespace Westkingdom\GoogleAPIExtensions\Internal;

use Westkingdom\GoogleAPIExtensions;

class Journal {
  protected $ctrl;
  protected $operationQueues;
  protected $existingState = array();

  const SETUP_QUEUE = 'create';
  const CREATION_QUEUE = 'create';
  const DEFAULT_QUEUE = 'default';
  const TEARDOWN_QUEUE = 'last';

  protected $queues = array(Journal::SETUP_QUEUE, Journal::CREATION_QUEUE, Journal::DEFAULT_QUEUE, Journal::TEARDOWN_QUEUE);

  function __construct($ctrl, $existingState) {
    $this->ctrl = $ctrl;
    $this->existingState = $existingState;
    $this->operationQueues = array();
  }

  function getExistingState() {
    return $this->existingState;
  }

  function hasOperation($other, $queueName = Journal::DEFAULT_QUEUE) {
    if (array_key_exists($queueName, $this->operationQueues)) {
      foreach ($this->operationQueues[$queueName] as $op) {
        if ($op->equals($other)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  function queue(Operation $op, $queueName = Journal::DEFAULT_QUEUE) {
    // TODO: validate that $queueName exists in QUEUES
    // Don't allow the same operations to pile up; we'll keep the
    // older one.
    if (!$this->hasOperation($op, $queueName)) {
      $this->operationQueues[$queueName][] = $op;
    }
  }

  // TODO: Operations should have a 'ready' test, and we should also
  // check the return value of 'run'.  We should return a list of all
  // operations that we ran without error.
  function executeQueue($queueName) {
    if (array_key_exists($queueName, $this->operationQueues)) {
      foreach ($this->operationQueues[$queueName] as $op) {
        // TODO: record the time we attempted to run $op
        $op->run();
      }
    }
  }

  // TODO: This should take the array of operations that was returned
  // from 'executeQueue', and only try to verify those.  Remove only
  // those items from the queue that verified.  The problem with this
  // idea is that we run in batch mode; we'd have to reassociate the
  // failure from the batch execute with the operation it belonged to.
  function verifyQueue($queueName) {
    $unfinished = array();
    if (array_key_exists($queueName, $this->operationQueues)) {
      foreach ($this->operationQueues[$queueName] as $op) {
        $verified = FALSE;
        if ($op->needsVerification()) {
          $verified = $op->verify();
        }
        // If the operation is verified, then run the 'verified'
        // method that matches the name of the run function for this
        // operation (with 'Verified' appended).
        if ($verified) {
          $verifiedMethodName = $op->getRunFunctionName() . 'Verified';
          if (method_exists($this, $verifiedMethodName)) {
            call_user_func_array(array($this, $verifiedMethodName), $op->getRunFunctionParameters());
          }
        }
        else {
          $unfinished[] = $op;
        }
      }
    }
    // Remove finished operations from the queue
    $this->operationQueues[$queueName] = $unfinished;
  }

  function findOpById($key) {
    foreach ($this->queues as $queueName) {
      $op = $this->findOpByIdInQueue($key, $queueName);
      if ($op) {
        return $op;
      }
    }
    return FALSE;
  }

  function findOpByIdInQueue($key, $queueName) {
    if (array_key_exists($queueName, $this->operationQueues)) {
      foreach ($this->operationQueues[$queueName] as $op) {
        if ($op->compareId($key)) {
          return $op;
        }
      }
    }
    return FALSE;
  }

  function execute() {
    $executionResults = array();
    // Run all of the operations in all of the queues
    foreach ($this->queues as $queueName) {
      $this->ctrl->begin();
      $this->executeQueue($queueName);
      // TODO: can we associate any failures in $batchResult with the op that it belongs to?
      $batchResult = $this->ctrl->complete(TRUE);
      if (!empty($batchResult)) {
        foreach ($batchResult as $batchKey => $batchInfo) {
          $op = $this->findOpByIdInQueue($batchKey, $queueName);
          if ($op) {
            $op->recordExecutionResult($batchInfo);
          }
        }
      }
      $executionResults[$queueName] = $batchResult;
    }
    // Verify each operation
    foreach ($this->queues as $queueName) {
      $this->verifyQueue($queueName);
    }
    return $executionResults;
  }

  function begin() {
  }

  function insertBranch($branch) {
    $op = new Operation(
      array($this->ctrl, "insertBranch"),
      array($branch),
      array($this->ctrl, "verifyBranch"),
      array($branch)
    );
    $this->queue($op, Journal::SETUP_QUEUE);
  }

  function insertBranchVerified($op, $branch) {
    if (!array_key_exists($branch, $this->existingState)) {
      $this->existingState[$branch] = array();
    }
  }

  function deleteBranch($branch) {
    $op = new Operation(
      array($this->ctrl, "deleteBranch"),
      array($branch)
    );
    $this->queue($op, Journal::TEARDOWN_QUEUE);
  }

  function deleteBranchVerified($op, $branch) {
    unset($this->existingState[$branch]);
  }

  function insertMember($branch, $officename, $group_id, $memberEmailAddress) {
    $op = new Operation(
      array($this->ctrl, "insertMember"),
      array($branch, $officename, $group_id, $memberEmailAddress),
      array($this->ctrl, "verifyMember"),
      array($branch, $officename, $group_id, $memberEmailAddress)
    );
    $this->queue($op);
  }

  function insertMemberVerified($op, $branch, $officename, $group_id, $memberEmailAddress) {
    // TODO: unique only. Should we sort as well?
    $this->existingState[$branch]['lists'][$officename]['members'][] = $memberEmailAddress;
  }

  function removeMember($branch, $officename, $group_id, $memberEmailAddress) {
    $op = new Operation(
      array($this->ctrl, "removeMember"),
      array($branch, $officename, $group_id, $memberEmailAddress)
    );
    $this->queue($op);
  }

  function removeMemberVerified($op, $branch, $officename, $group_id, $memberEmailAddress) {
    // TODO: unique only. Should we sort as well?
    unset($this->existingState[$branch]['lists'][$officename]['members'][$memberEmailAddress]);
  }

  function insertGroupAlternateAddress($branch, $officename, $group_id, $alternateAddress) {
    $op = new Operation(
      array($this->ctrl, "insertGroupAlternateAddress"),
      array($branch, $officename, $group_id, $alternateAddress),
      array($this->ctrl, "verifyGroupAlternateAddress"),
      array($branch, $officename, $group_id, $alternateAddress)
    );
    $this->queue($op);
  }

  function insertGroupAlternateAddressVerified($op, $branch, $officename, $group_id, $alternateAddress) {
    // TODO: unique only. Should we sort as well?
    $this->existingState[$branch]['lists'][$officename]['properties']['alternate-addresses'][] = $alternateAddress;
  }

  function removeGroupAlternateAddress($branch, $officename, $group_id, $alternateAddress) {
    $op = new Operation(
      array($this->ctrl, "removeGroupAlternateAddress"),
      array($branch, $officename, $group_id, $alternateAddress)
    );
    $this->queue($op);
  }

  function removeGroupAlternateAddressVerified($op, $branch, $officename, $group_id, $alternateAddress) {
    unset($this->existingState[$branch]['lists'][$officename]['properties']['alternate-addresses'][$alternateAddress]);
  }

  function insertOffice($branch, $officename, $properties) {
    $op = new Operation(
      array($this->ctrl, "insertOffice"),
      array($branch, $officename, $properties),
      array($this->ctrl, "verifyOffice"),
      array($branch, $officename, $properties)
    );
    $this->queue($op, Journal::CREATION_QUEUE);
    $op = new Operation(
      array($this->ctrl, "configureOffice"),
      array($branch, $officename, $properties),
      array($this->ctrl, "verifyOfficeConfiguration"),
      array($branch, $officename, $properties)
    );
    $this->queue($op);
  }

  function insertOfficeVerified($branch, $officename, $properties) {
    foreach (array('group-name', 'group-email') as $key) {
      $this->existingState[$branch]['lists'][$officename]['properties'][$key] = $properties[$key];
    }
  }

  function configureOfficeVerified($op, $branch, $officename, $properties) {
    // TODO: At the moment, all configuration is hardcoded.  When that changes,
    // we will need to update our state here as well.
  }

  function deleteOffice($branch, $officename, $properties) {
    $op = new Operation(
      array($this->ctrl, "deleteOffice"),
      array($branch, $officename, $properties)
    );
    $this->queue($op);
  }

  function deleteOfficeVerified($op, $branch, $officename, $properties) {
    unset($this->existingState[$branch]['lists'][$officename]);
  }

  function complete() {
  }
}
