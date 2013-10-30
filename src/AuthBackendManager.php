<?php
namespace Wsconnect;

use Drupal\wconsumer\Wconsumer;
use Drupal\wconsumer\Service\Service;
use Wsconnect\AuthBackend\AuthBackendInterface;



class AuthBackendManager extends \ArrayObject {
  private $backends;



  public function __construct($user) {
    $knownBackends = array(
      AuthBackend\Github::getClass() => Wconsumer::$github,
      AuthBackend\Google::getClass() => Wconsumer::$google,
      AuthBackend\Linkedin::getClass() => Wconsumer::$linkedin,
    );

    /** @var AuthBackendInterface[] $backends */
    $backends = array();

    /** @var Service $service */
    foreach ($knownBackends as $class => $service) {
      $backends[$service->getName()] = new $class($service, $user->uid);
    }

    $this->backends = $backends;
  }

  /**
   * @param bool $onlyActive
   * @param string $name
   * @return null|AuthBackendInterface|AuthBackendInterface[]
   */
  public function get($name, $onlyActive = true) {
    if ($name !== 'all') {
      if ($onlyActive && !$this->active($name)) {
        return null;
      }

      return @$this->backends[$name];
    }
    else {
      $activeBackends = array();

      foreach (array_keys($this->backends) as $name) {
        if ($backend = $this->get($name, $onlyActive)) {
          $activeBackends[$name] = $backend;
        }
      }

      return $activeBackends;
    }
  }

  public function enable($name, $enable = true) {
    $this->backendsState($name, $enable);
  }

  public function disable($name) {
    $this->enable($name, false);
  }

  public function enabled($name) {
    return $this->backendsState($name);
  }

  public function active($name) {
    $backend = @$this->backends[$name];

    return
      isset($backend) &&
      $this->enabled($name) &&
      $backend->getService()->isActive();
  }

  private function backendsState($backend, $state = null) {
    $states = $this->data('backends_state');

    if (func_num_args() > 1) {
      $states[$backend] = (bool)$state;
      $this->data('backends_state', $states);
      return null;
    }
    else {
      return (bool)@$states[$backend];
    }
  }

  private function data($key, $value = null) {
    $key = "wsconnect_{$key}";

    if (func_num_args() > 1) {
      variable_set($key, $value);
      return null;
    }
    else {
      return variable_get($key);
    }
  }
}