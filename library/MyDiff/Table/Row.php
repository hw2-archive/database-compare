<?php

class MyDiff_Table_Row extends MyDiff_Item{

  public $uid;
  public $data;
  private $_table;

  public function __construct(&$table, $data)
  {
    $this->_table = $table;
    $this->data = $data;
    $this->uid = $this->generateUid();
  }

  public function hasPrimary()
  {
    $columns = $this->getPrimaryColumns();
    return (!empty($columns));
  }

  public function getPrimaryColumns()
  {
    return $this->_table->getPrimaryColumns();
  }

  public function generateUid()
  {
    $data = ($this->hasPrimary())? array_intersect_key($this->data, array_fill_keys($this->getPrimaryColumns(), null)) : $this->data;
    $uid = md5(serialize($data));
    return $uid;
  }

  public function prune()
  {
    $this->data = null;
  }

  public function getValueDiff($columnName)
  {
    $diffs = $this->getDiffs('MyDiff_Diff_Table_Row_Value');
    foreach($diffs AS $diff)
      if($diff->columnName == $columnName)
        return $diff;

    return false;
  }

  public function hasValueDiff($columnName)
  {
    $diff = $this->getValueDiff($columnName);
    return !(empty($diff));
  }




}
