<?php

class MyDiff_Diff_Abstract{
  
  public $tags = array('diff');
  
  public function addTag($tag)
  {
    if(!in_array($tag, $this->tags))
      $this->tags[] = $tag;
  }
  
}
