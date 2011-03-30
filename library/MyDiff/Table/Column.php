<?php

class MyDiff_Table_Column extends MyDiff_Item{

  public $name;
  public $metadata;
  
  public function __construct($metadata = null)
  {
    $this->metadata = $metadata;
    
    if(is_array($metadata) && array_key_exists('COLUMN_NAME', $metadata))
      $this->name = $metadata['COLUMN_NAME'];
  }

}
