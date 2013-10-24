<?php
namespace Wsconnect\AuthBackend;



class Linkedin extends AuthBackend {
  public function scopes() {
    return array('r_basicprofile', 'r_emailaddress');
  }

  public function uniqueUserId() {
    $linkedinUserId = $this->userinfo('id');
    $globalUserId = "com.linkedin.{$linkedinUserId}";
    return $globalUserId;
  }

  public function userLogin() {
    return $this->userinfo('formattedName');
  }

  public function userEmail() {
    return $this->userinfo('emailAddress');
  }

  private function userinfo($field) {
    return $this->fetchField('people/~:(id,formatted-name,email-address)?format=json', $field);
  }
}