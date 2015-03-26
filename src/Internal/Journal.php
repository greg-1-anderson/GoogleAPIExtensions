<?php

namespace Westkingdom\GoogleAPIExtensions\Internal;

use Westkingdom\GoogleAPIExtensions;

class Journal {
  protected $ctrl;
  protected $operationQueues;

  const CREATION_QUEUE = 'create';
  const DEFAULT_QUEUE = 'default';
  const LAST_QUEUE = 'last';

  protected $queues = array(Journal::CREATION_QUEUE, Journal::DEFAULT_QUEUE, Journal::LAST_QUEUE);

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
    // TODO: this is a no-op, so we do nothing for now.
    return $this->ctrl->insertBranch($branch);
  }

  function deleteBranch($branch) {
    // TODO: this is a no-op, so we do nothing for now.
    return $this->ctrl->deleteBranch($branch);
  }

  function insertMember($branch, $officename, $memberEmailAddress) {
    $op = new Operation(
      array($this->ctrl, "insertMember"),
      array($branch, $officename, $memberEmailAddress),
      array($this->ctrl, "verifyMember"),
      array($branch, $officename, $memberEmailAddress)
    );
    $this->queue($op);
    // return $this->ctrl->insertMember($branch, $officename, $memberEmailAddress);
  }

  function removeMember($branch, $officename, $memberEmailAddress) {
    $op = new Operation(
      array($this->ctrl, "removeMember"),
      array($branch, $officename, $memberEmailAddress)
    );
    $this->queue($op);
    // return $this->ctrl->removeMember($branch, $officename, $memberEmailAddress);
  }

  function insertGroupAlternateAddress($branch, $officename, $alternateAddress) {
    $op = new Operation(
      array($this->ctrl, "insertGroupAlternateAddress"),
      array($branch, $officename, $alternateAddress),
      array($this->ctrl, "verifyGroupAlternateAddress"),
      array($branch, $officename, $alternateAddress)
    );
    $this->queue($op);
    //return $this->ctrl->insertGroupAlternateAddress($branch, $officename, $alternateAddress);
  }

  function removeGroupAlternateAddress($branch, $officename, $alternateAddress) {
    $op = new Operation(
      array($this->ctrl, "removeGroupAlternateAddress"),
      array($branch, $officename, $alternateAddress)
    );
    $this->queue($op);
    //return $this->ctrl->removeGroupAlternateAddress($branch, $officename, $alternateAddress);
  }

  function insertOffice($branch, $officename, $properties) {
    $op = new Operation(
      array($this->ctrl, "insertOffice"),
      array($branch, $officename, $properties),
      array($this->ctrl, "verifyOffice"),
      array($branch, $officename, $properties)
    );
    $this->queue($op, Journal::CREATION_QUEUE);
    return $this->ctrl->insertOffice($branch, $officename, $properties);
  }

  function deleteOffice($branch, $officename, $properties) {
    return $this->ctrl->deleteOffice($branch, $officename, $properties);
  }

  function complete() {
  }
}
