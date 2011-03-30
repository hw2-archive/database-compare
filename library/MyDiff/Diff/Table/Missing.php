<?php

class MyDiff_Diff_Table_Missing extends MyDiff_Diff_Table{
  
  public function __construct()
  {
    $this->addTag('missing');
  }
  
}
