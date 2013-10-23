<?php
namespace Wsconnect\AuthBackend;

use Guzzle\Http\Client;



class Google extends AuthBackend {
  public function scopes() {
    return array('openid', 'profile', 'email');
  }

  public function uniqueUserId() {
    $googleUserId = $this->userinfo('sub');
    $globalUserId = "com.google.{$googleUserId}";
    return $globalUserId;
  }

  public function userLogin() {
    return $this->userinfo('name');
  }

  public function userEmail() {
    return $this->userinfo('email');
  }

  private function userinfo($field) {
    return $this->fetchField('/oauth2/v3/userinfo', $field);
  }
}