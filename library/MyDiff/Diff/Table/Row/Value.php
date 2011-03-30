<?php

class MyDiff_Diff_Table_Row_Value extends MyDiff_Diff_Table_Column{
  
  public $columnName;
  public $value;
  public $compare;
  
  public function __construct($columnName, $value, $compare)
  {
    $this->columnName = $columnName;
    $this->value = $value;
    $this->compare = $compare;
    
    $this->addTag('value');
  }
  
}
