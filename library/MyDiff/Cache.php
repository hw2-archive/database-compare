<?php

class MyDiff_Cache{

  static public function init()
  {
    return Zend_Cache::factory('Core', 'File', array('lifetime' => null, 'automatic_serialization' => true), array('cache_dir' => APPLICATION_PATH . '/../cache'));
  }

}