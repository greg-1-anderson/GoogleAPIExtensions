<?php

namespace Westkingdom\GoogleAPIExtensions;

/**
 * A class that can be used like a Google_Http_Request
 * in unit tests.  It accumulates all of the requests
 * added to it, and will later return a simplified
 * list of requests for use in assertions.
 */
class BatchStandin
{
  /** @var array service requests to be executed. */
  private $requests = array();

  public function __construct()
  {
  }

  /**
   * Stash our requests, just like Google_Http_Batch does.
   */
  public function add(\Google_Http_Request $request, $key = false)
  {
    if (false == $key) {
      $key = mt_rand();
    }

    $this->requests[$key] = $request;
  }

  /**
   * Return all of the stashed requests.
   */
  public function getRequests() {
    return $this->requests;
  }

  /**
   * Return only the 'url' and 'body' of each request.
   */
  public function getSimplifiedRequests() {
    $result = array();
    foreach ($this->requests as $request) {
      $request->setBaseComponent('');
      $item = array(
        'url' => $request->getUrl(),
      );
      if (null != $request->getPostBody()) {
        $item['body'] = $request->getPostBody();
      }
      $result[] = $item;
    }
    return $result;
  }
}
