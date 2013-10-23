<?php
namespace Wsconnect;

use Drupal\wconsumer\Service\Base as Service;



class Hooks {
  private $wsconnect;



  public function __construct(Wsconnect $wsconnect) {
    $this->wsconnect = $wsconnect;
  }

  public function defineRequiredScopes(Service $service) {
    $backend = @$this->wsconnect->backends[$service->getName()];
    if (!isset($backend)) {
      return null;
    }

    return $backend->scopes();
  }

  public function defineBlocks() {
    $blocks = array();

    foreach ($this->wsconnect->backends as $backend) {
      $service = $backend->getService();

      $blocks["{$service->getName()}-link"] = array(
        'info' => t(
          "Connect with @service (wconsumer)",
          array('@service' => $service->getMeta()->niceName)
        ),
      );
    }

    return $blocks;
  }

  public function viewBlock($name) {
    $service = null;
    {
      list($serviceName, $block) = explode('-', $name, 2);

      if ($block !== 'link') {
        return null;
      }

      $backend = @$this->wsconnect->backends[$serviceName];
      if (!isset($backend)) {
        return null;
      }

      $service = $backend->getService();
      if (!$service->isActive()) {
        return null;
      }
    }

    return l(
      t("Connect with @service", array('@service' => $service->getMeta()->niceName)),
      sprintf('wconsumer/auth/%s/', rawurlencode($service->getName())),
      array('query' => array('destination' => sprintf('/wsconnect/connect/%s', rawurlencode($service->getName()))))
    );
  }

  public function showBlocksOnLoginForm(&$form) {
    foreach (array_keys($this->defineBlocks()) as $blockName) {
      $block = $this->viewBlock($blockName);
      if ($block) {
        $form[$blockName] = array(
          '#theme' => 'item_list',
          '#items' => array(
            'data' => $block,
          ),
          '#weight' => 1,
        );
      }
    }
  }

  public function fillRegisterFormWithUserData(&$form) {
    $backend = $this->wsconnect->currentlyConnectingWith();
    if (!isset($backend)) {
      return;
    }

    // Shorthands to check and set default form field values
    $defaultValueDefinedFor = null;
    $setDefaultValueFor = null;
    {
      $default = function($field, $value = NULL) use(&$form) {
        if (func_num_args() > 1) {
          $form['account'][$field]['#default_value'] = $value;
        }
        return @$form['account'][$field]['#default_value'];
      };

      $defaultValueDefinedFor = function($field) use($default) {
        return $default($field) != '';
      };

      $setDefaultValueFor = function($field, $value) use($default) {
        $default($field, $value);
      };
    }

    if (!$defaultValueDefinedFor('name') || !$defaultValueDefinedFor('mail')) {
      // Default field value substution is a completely optional thing. Nobody will die if it fail.
      // On other hand it may fail relatively often due to network issues. So we'd better suppress
      // all possible exceptions an do the best we can.

      if (!$defaultValueDefinedFor('name')) {
        $this->suppressExceptions(function() use($backend, $setDefaultValueFor) {
          $setDefaultValueFor('name', $backend->userLogin());
        });
      }

      if (!$defaultValueDefinedFor('mail')) {
        $this->suppressExceptions(function() use($backend, $setDefaultValueFor) {
          $setDefaultValueFor('mail', $backend->userEmail());
        });
      }
    }
  }

  public function storeCredentialsFromSessionToUserAccount($userId) {
    $backend = $this->wsconnect->currentlyConnectingWith();
    if (!isset($backend)) {
      return null;
    }

    $service = $backend->getService();

    $sessionCredentials = $service->getCredentials(NULL);
    if (!isset($sessionCredentials)) {
      return;
    }

    $service->setCredentials($sessionCredentials, $userId);
  }

  public function insertConnectButtonsIntoProfileForm(&$form) {
    global $user;

    foreach ($this->wsconnect->backends as $backend) {
      $service = $backend->getService();
      $serviceName = $service->getName();

      if (!isset($form['web_services'][$serviceName])) {
        continue;
      }

      $connected = (bool)$this->wsconnect->authmap($user, $service);

      $verb = null;
      $label = null;
      $text = null;
      if ($connected) {
        $verb = 'disconnect';
        $label = 'Disconnect @service';
        $text = 'If you no longer want to login using your @service account you can disconnect from it.';
      }
      else {
        $verb = 'connect';
        $label = 'Connect with @service';
        $text = 'You can login with one click using your @service account instead of providing your email and password
                 every time. To achieve this click the button below.';
      }

      $placeholders = array('@service' => $service->getMeta()->niceName);
      $text  = t($text, $placeholders);
      $label = t($label, $placeholders);

      $form['web_services'][$serviceName]['connect_button'] = array(
        '#markup' =>
          '<p></p><p>
            '.$text.'
      </p>'.
          l($label, sprintf("wsconnect/%s/%s/", $verb, rawurlencode($serviceName)), array(
            'attributes' => array('class' => 'button'),
            'query' => drupal_get_destination(),
          )),
      );
    }
  }

  private function suppressExceptions($function) {
    $result = null;

    try {
      $result = $function();
    }
    catch (\Exception $e) {
      // do nothing
    }

    return $result;
  }
}