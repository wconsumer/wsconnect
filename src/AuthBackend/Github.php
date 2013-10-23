<?php
namespace Wsconnect\AuthBackend;

use Guzzle\Http\Client;



class Github extends AuthBackend {
  public function scopes() {
    return array('user:email');
  }

  public function uniqueUserId() {
    return $this->fetchField('/user', 'html_url');
  }

  public function userLogin() {
    return $this->fetchField('/user', 'login');
  }

  public function userEmail() {
    return $this->fetchField('/user/emails', 0);
  }
}