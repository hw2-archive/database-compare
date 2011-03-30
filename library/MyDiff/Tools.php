<?php

class MyDiff_Tools{

  /** 
   * Cycle through the given multidimensional array and return the key
   * of an array or variable of an object
   */ 
  static public function extractKey(Array $array, String $key)
  {
    $values = array();
    foreach($array AS $object)
    {
      if(is_object($object))
        $values[] = $bject->$key;
      elseif(is_array($object))
        $values[] = $object[$key];
    }
    return $values;    
  }

}
