<?php

namespace Westkingdom\GoogleAPIExtensions\Internal;

use Westkingdom\GoogleAPIExtensions;

class Journal {
  protected $ctrl;
  protected $operationQueues;

  const SETUP_QUEUE = 'create';
  const CREATION_QUEUE = 'create';
  const DEFAULT_QUEUE = 'default';
  const TEARDOWN_QUEUE = 'last';

  protected $queues = array(Journal::SETUP_QUEUE, Journal::CREATION_QUEUE, Journal::DEFAULT_QUEUE, Journal::TEARDOWN_QUEUE);

  function queue(Operation $op, $queueName = Journal::DEFAULT_QUEUE) {
    // TODO: validate that $queueName exists in QUEUES
    $this->operationQueues[$queueName][] = $op;
  }

  // TODO: Operations should have a 'ready' test, and we should also
  // check the return value of 'run'.  We should return a list of all
  // operations that we ran without error.
  function executeQueue($queueName) {
    if (array_key_exists($queueName, $this->operationQueues)) {
      foreach ($this->operationQueues[$queueName] as $op) {
        $op->run();
      }
    }
  }

  // TODO: This should take the array of operations that was returned
  // from 'executeQueue', and only try to verify those.  Remove only
  // those items from the queue that verified.
  function verifyQueue($queueName) {
    $unfinished = array();
    if (array_key_exists($queueName, $this->operationQueues)) {
      foreach ($this->operationQueues[$queueName] as $op) {
        if (!$op->verify()) {
          $unfinished[] = $op;
        }
      }
    }
    // Remove finished operations from the queue
    $this->operationQueues[$queueName] = $unfinished;
  }

  function execute() {
    // Run all of the operations in all of the queues
    foreach ($this->queues as $queueName) {
      $this->ctrl->begin();
      $this->executeQueue($queueName);
      $this->ctrl->complete();
    }
    // Verify each operation
    foreach ($this->queues as $queueName) {
      $this->verifyQueue($queueName);
    }
  }

  function __construct($ctrl) {
    $this->ctrl = $ctrl;
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

  function deleteBranch($branch) {
    $op = new Operation(
      array($this->ctrl, "deleteBranch"),
      array($branch)
    );
    $this->queue($op, Journal::TEARDOWN_QUEUE);
  }

  function insertMember($branch, $officename, $memberEmailAddress) {
    $op = new Operation(
      array($this->ctrl, "insertMember"),
      array($branch, $officename, $memberEmailAddress),
      array($this->ctrl, "verifyMember"),
      array($branch, $officename, $memberEmailAddress)
    );
    $this->queue($op);
  }

  function removeMember($branch, $officename, $memberEmailAddress) {
    $op = new Operation(
      array($this->ctrl, "removeMember"),
      array($branch, $officename, $memberEmailAddress)
    );
    $this->queue($op);
  }

  function insertGroupAlternateAddress($branch, $officename, $alternateAddress) {
    $op = new Operation(
      array($this->ctrl, "insertGroupAlternateAddress"),
      array($branch, $officename, $alternateAddress),
      array($this->ctrl, "verifyGroupAlternateAddress"),
      array($branch, $officename, $alternateAddress)
    );
    $this->queue($op);
  }

  function removeGroupAlternateAddress($branch, $officename, $alternateAddress) {
    $op = new Operation(
      array($this->ctrl, "removeGroupAlternateAddress"),
      array($branch, $officename, $alternateAddress)
    );
    $this->queue($op);
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

  function deleteOffice($branch, $officename, $properties) {
    $op = new Operation(
      array($this->ctrl, "deleteOffice"),
      array($branch, $officename, $properties)
    );
    $this->queue($op);
  }

  function complete() {
  }
}
