<?php
namespace Wsconnect\AuthBackend;

use Drupal\wconsumer\Service\Service;



interface AuthBackendInterface {
  /** @return Service */
  public function getService();
  public function setService(Service $api);
  public function setUserId($userId);

  public function scopes();
  public function uniqueUserId();
  public function userLogin();
  public function userEmail();
}