<?php
namespace Wsconnect;

use Drupal\wconsumer\Service\Service;
use Drupal\wconsumer\Service\Exception\NoUserCredentials;
use Drupal\wconsumer\Wconsumer;
use Wsconnect\AuthBackend\AuthBackendInterface;



class Wsconnect {
  public $hooks;
  public $backends;

  private static $instance;



  public static function instance() {
    if (!isset(self::$instance)) {
      global $user;
      self::$instance = new Wsconnect(new AuthBackendManager($user));
    }

    return self::$instance;
  }

  public function __construct(AuthBackendManager $backends) {
    $this->backends = $backends;
    $this->hooks = new Hooks($this);
  }

  public function connect($service) {
    try {
      $backend = $this->backend($service);
      $userGuid = $this->userGuid($backend);

      $this->currentlyConnectingWith($backend);

      $this->connectCurrentUser($backend->getService(), $userGuid) or
      $this->loginUser($userGuid) or
      $this->registerUser($backend, $userGuid);
    }
    catch (UserSpaceError $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
    catch (\Exception $e) {
      drupal_set_message('An unknown error occurred. Please try again later or connect with the site administrator.', 'error');
    }

    drupal_goto();
  }

  public function disconnect($service) {
    global $user;

    if (!user_is_logged_in()) {
      drupal_goto();
    }

    if (!empty($user->data['wsconnect_autoreg'])) {
      if (!empty($_GET['confirm_account_removal'])) {
        user_delete($user->uid);
        unset($_GET['destination']);
      }
      else {
        drupal_set_message(
          t('Disconnecting would lead to this account removal. Would you like to proceed?<p></p>').
          $this->hooks->renderConnectButton('OK', 'disconnect', $service, true).
          $this->hooks->renderButton('Cancel', '/', array(), array('onclick' => 'this.parentNode.style.display="none"; return false;')),
          'warning'
        );
      }

      drupal_goto();
    }

    $service = Wconsumer::instance()->services->{$service};

    $this->authmap($user, $service, null);

    drupal_set_message(t(
      'You are no longer connected with @service',
      array('@service' => $service->getMeta()->niceName)
    ));

    drupal_goto();
  }

  public function authmap($user = null, Service $service = null, $userGuid = null) {
    $serviceId = function(Service $service) {
      return "wsconnect-{$service->getName()}";
    };

    if (func_num_args() < 3) {
      if (!isset($user) || !isset($service)) {
        throw new \BadMethodCallException();
      }

      /** @noinspection PhpUndefinedFieldInspection */
      $uid = $user->uid;

      $userGuid =
        db_select('{authmap}', 'am')
          ->fields('am', array('authname'))
          ->condition('module', $serviceId($service))
          ->condition('uid', $uid)
          ->range(0, 1)
          ->execute()
          ->fetchField();
    }
    else if (!isset($user) && !isset($service)) {
      if (!isset($userGuid)) {
        throw new \BadMethodCallException();
      }

      return user_external_load($userGuid);
    }
    else {
      if (!isset($user) || !isset($service)) {
        throw new \BadMethodCallException();
      }

      if (isset($userGuid) && $this->authmap(null, null, $userGuid)) {
        throw new UserSpaceError(t(
          "Sorry, can't connect you with the specified service account because ".
          "it is already connected with another account. Please disconnect it first."
        ));
      }

      user_set_authmaps($user, array("authname_{$serviceId($service)}" => $userGuid));
    }

    return $userGuid;
  }

  public function currentlyConnectingWith(AuthBackendInterface $backend = null) {
    static $sessionKey = 'wsconnect_currently_connecting_with';

    if (func_num_args() > 0) {
      $_SESSION[$sessionKey] = $backend->getService()->getName();
    }

    return $this->backends->get($_SESSION[$sessionKey]);
  }

  private function backend($service) {
    $backend = $this->backends->get($service);

    if (!isset($backend)) {
      $knownService = Wconsumer::instance()->services->get($service);
      $serviceNiceName = $knownService ? $knownService->getMeta()->niceName : $service;
      throw new UserSpaceError(t("Sorry, @service it's not currently supported.", array('@service' => $serviceNiceName)));
    }

    return $backend;
  }

  private function userGuid(AuthBackendInterface $backend) {
    try {
      $userGuid = $backend->uniqueUserId();
    }
    catch (NoUserCredentials $e) {
      $frontendLib = DRUPAL_ROOT.'/'.drupal_get_path('module', 'wconsumer_ui').'/wconsumer_frontend_form.inc';
      require_once($frontendLib);
      $_GET['destination'] = $_SERVER['REQUEST_URI'];
      _wconsumer_frontend_auth($backend->getService()->getName());
      die; // just to make sure we can't pass through this
    }

    return $userGuid;
  }

  private function connectCurrentUser(Service $service, $userGuid) {
    global $user;

    if (!user_is_logged_in()) {
      return false;
    }

    $this->authmap($user, $service, $userGuid);
    drupal_set_message(t("You are now connected with your {$service->getMeta()->niceName} account."));

    return true;
  }

  private function loginUser($userGuid) {
    $user = $this->authmap(null, null, $userGuid);
    if (!$user) {
      return false;
    }

    user_login_submit(array(), $form_state = array('uid' => $user->uid));

    return true;
  }

  private function registerUser(AuthBackendInterface $backend, $userGuid) {
    $login = $backend->userLogin();
    $email = $backend->userEmail();

    // Register user with built-in register form
    drupal_form_submit('user_register_form', $form_state = array('values' => array(
      'name' => $login,
      'mail' => $email,
      'pass' => array(
        'pass1' => $pass = user_password(),
        'pass2' => $pass,
      )
    )));

    // Find user account info
    $account = user_load_by_name($login);

    $placeholders = array('@service' => $backend->getService()->getMeta()->niceName);

    // Redirect user to register page on registration fail so he can choose hist username and email and try
    // to register again
    if (form_get_errors() || !$account) {
      // If username or email is taken or some other registration error occurs then there will be errors shown coming
      // from register form. We don't want to show them to user for this time b/c it would be confusing. Instead
      // we show our warning message.
      form_clear_error();
      drupal_get_messages(); // clear messages

      drupal_set_message(
        t('No linked account found. Please create a new account and next time you will be able to log in to
         it with @service. If you already have an account here please sign in and link it to your @service account
         on My Account page.', $placeholders),
        'warning'
      );

      drupal_goto('user/register');
    }

    user_save($account, array('data' => array('wsconnect_autoreg' => true)));

    $GLOBALS['user'] = $account;
    $this->connectCurrentUser($backend->getService(), $userGuid);

    // Make user happy
    drupal_set_message(t('You are now registered with your @service username and email.', $placeholders));
    drupal_goto('user');
  }
}