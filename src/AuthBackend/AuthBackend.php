<?php
namespace Wsconnect\AuthBackend;

use Drupal\wconsumer\Service\Base as Service;



abstract class AuthBackend implements AuthBackendInterface {
  /** @var Service */
  protected $service;
  protected $userId;
  private $cache;



  public function __construct(Service $service, $userId) {
    $this->setService($service);
    $this->setUserId($userId);
  }

  public function getService() {
    return $this->service;
  }

  public function setService(Service $service) {
    $this->service = $service;
  }

  public function setUserId($userId) {
    $this->userId = $userId;
  }

  protected function fetchField($url, $field) {
    $response = $this->api($url);

    if (!isset($response[$field])) {
      throw new \RuntimeException("Required field '{$field}' is not present in service response");
    }

    return $response[$field];
  }

  private function api($url) {
    $scopes = $this->scopes();

    $cacheId = join('|', array($url, $this->userId, join(',', $scopes)));

    $response = null;
    if (!isset($this->cache[$cacheId])) {
      $api = $this->service->api($this->userId, $scopes);
      $response = $api->get($url)->send()->json();
      $this->cache[$cacheId] = $response;
    }
    else {
      $response = $this->cache[$cacheId];
    }

    return $response;
  }
}