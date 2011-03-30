<?php

class MyDiff_Diff_Table_Row_Missing extends MyDiff_Diff_Table_Column{
  
  public function __construct()
  {
    $this->addTag('missing');
  }
  
}
