<?php

class MyDiff_Diff_Table_Column_Property extends MyDiff_Diff_Table_Column{
    
  public $metaKey;
  public $metaValue;
    
  public function __construct($metaKey, $metaValue)
  {
    $this->metaKey = $metaKey;
    $this->metaValue = $metaValue;
    
    $this->addTag('property');
  }
  
}
