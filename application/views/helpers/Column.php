<?php

class Zend_View_Helper_Column extends Zend_View_Helper_Abstract{

  public function column($string)
  {
    $string = $this->view->truncate($string, 200);
    $string = htmlentities($string);

    if($string === '')
      $string = '&nbsp';

    return $string;
  }

}