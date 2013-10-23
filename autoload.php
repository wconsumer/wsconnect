<?php
spl_autoload_register(function($class) {
  static $prefix = 'Wsconnect\\';

  if (strpos($class, $prefix) === 0) {
    $class = substr($class, strlen($prefix));

    $filename = __DIR__.'/src/'.str_replace('\\', "/", $class).'.php';
    if (file_exists($filename)) {
      require_once($filename);
    }
  }
});