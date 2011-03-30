<?php

class Zend_View_Helper_Truncate extends Zend_View_Helper_Abstract{

  public function truncate($string, $length = 100, $suffix = '...')
  {
    $string = (string) $string;

    if(strlen($string) > $length)
      $string = substr($string, 0, $length) . $suffix;

    return $string;
  }

}