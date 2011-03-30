<?php

class MyDiff_Diff_Table_Row_New extends MyDiff_Diff_Table_Column{
  
  public function __construct()
  {
    $this->addTag('new');
  }
  
}
