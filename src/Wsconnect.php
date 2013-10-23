<?php
namespace Wsconnect;

use Drupal\wconsumer\Service\Base as Service;
use Drupal\wconsumer\Service\Exception\NoUserCredentials;
use Drupal\wconsumer\Wconsumer;
use Wsconnect\AuthBackend\AuthBackendInterface;



class Wsconnect {
  private static $instance;

  /** @var Hooks */
  public $hooks;

  /** @var  AuthBackendInterface[] */
  public $backends;



  public static function instance() {
    global $user;

    if (!isset(self::$instance)) {
      $wsconnect = new self();

      $wsconnect->backends = array();
      if (Wconsumer::$github->isActive()) {
        $wsconnect->backends[Wconsumer::$github->getName()] = new AuthBackend\Github(Wconsumer::$github, $user->uid);
      }
      if (Wconsumer::$google->isActive()) {
        $wsconnect->backends[Wconsumer::$google->getName()] = new AuthBackend\Github(Wconsumer::$google, $user->uid);
      }

      $wsconnect->hooks = new Hooks($wsconnect);

      self::$instance = $wsconnect;
    }

    return self::$instance;
  }

  public function connect($service) {
    try {
      $backend = $this->backend($service);
      $userGuid = $this->userGuid($backend);

      $this->currentlyConnectingWith($backend);

      $this->connectCurrentUser($service, $userGuid) or
      $this->loginUser($userGuid) or
      $this->registerUser($backend, $userGuid);
    }
    catch (UserSpaceError $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
    catch (\Exception $e) {
      drupal_set_message('An unknown error occurred. Please try again later or connect with the site administrator.');
    }

    drupal_goto();
  }

  public function disconnect($service) {
    global $user;

    if (!user_is_logged_in()) {
      return;
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
    $serviceId = "wsconnect-{$service->getName()}";

    if (func_num_args() < 3) {
      if (!isset($user) || !isset($service)) {
        throw new \BadMethodCallException();
      }

      /** @noinspection PhpUndefinedFieldInspection */
      $uid = $user->uid;

      $userGuid =
        db_select('{authmap}', 'am')
          ->fields('am', array('authname'))
          ->condition('module', $serviceId)
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

      if ($this->authmap(null, null, $userGuid)) {
        throw new UserSpaceError(t(
          "Sorry, can't connect you with the specified service account because ".
          "it is already connected with another account. Please disconnect it first."
        ));
      }

      user_set_authmaps($user, array("authname_{$serviceId}" => $userGuid));
    }

    return $userGuid;
  }

  public function currentlyConnectingWith(AuthBackendInterface $backend = null) {
    static $sessionKey = 'wsconnect_currently_connecting_with';

    if (func_num_args() > 0) {
      $_SESSION[$sessionKey] = $backend->getService()->getName();
    }

    return @$this->backends[$_SESSION[$sessionKey]];
  }

  private function backend($service) {
    $backend = @$this->backends[$service];

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
      _wconsumer_frontend_auth($backend->getService()->getName());
      die; // just to make sure we can't to pass through this
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

    $GLOBALS['user'] = $account;
    $this->connectCurrentUser($backend->getService(), $userGuid);

    // Make user happy
    drupal_set_message(t('You are now registered with your @service username and email.', $placeholders));
    drupal_goto('user');
  }
}