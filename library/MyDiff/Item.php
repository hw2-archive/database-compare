<?php

class MyDiff_Item{
  
  public $diffs = array();

  public function addDiff(MyDiff_Diff_Abstract $diff)
  {
    $this->diffs[] = $diff;
  }
  
  public function hasDiffs($type = null)
  {
    $diffs = $this->getDiffs($type);
    return (!empty($diffs));
  }
  
  public function getDiffs($type = null)
  {
    if($type === null)
    {
      return $this->diffs;
    }
    else
    {
      $diffs = array();
      foreach($this->diffs AS $diff)
        if($diff instanceof $type)
          $diffs[] = $diff;
          
      return $diffs;
    }
  }
  
  public function getAllTags()
  {
    $tags = array();
    
    foreach($this->diffs AS $diff)
      $tags = array_merge($tags, $diff->tags);
    
    return $tags;
  }

}
