<?php
namespace Wsconnect;



class FullAccessWrapper {
  protected $_self;
  protected $_refl;



  public function __construct($self) {
    $this->_self = $self;
    $this->_refl = new \ReflectionObject( $self );
  }

  public function __call($method, $args) {
    $mrefl = $this->_refl->getMethod($method);
    $mrefl->setAccessible(TRUE);
    return $mrefl->invokeArgs($this->_self, $args);
  }

  public function __set($name, $value) {
    $prefl = $this->_refl->getProperty($name);
    $prefl->setAccessible(TRUE);
    $prefl->setValue($this->_self, $value);
  }

  public function __get($name) {
    $prefl = $this->_refl->getProperty($name);
    $prefl->setAccessible(TRUE);
    return $prefl->getValue($this->_self);
  }

  public function __isset($name) {
    $value = $this->__get($name);
    return isset($value);
  }
}