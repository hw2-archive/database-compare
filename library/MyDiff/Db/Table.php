<?php

class MyDiff_Db_Table extends Zend_Db_Table{
  
  public $noPrimaryKey = false;

  protected function _setupPrimaryKey()
  {
      if (!$this->_primary) {
          $this->_setupMetadata();
          $this->_primary = array();
          foreach ($this->_metadata as $col) {
              if ($col['PRIMARY']) {
                  $this->_primary[ $col['PRIMARY_POSITION'] ] = $col['COLUMN_NAME'];
                  if ($col['IDENTITY']) {
                      $this->_identity = $col['PRIMARY_POSITION'];
                  }
              }
          }
          // if no primary key was specified and none was found in the metadata
          // then throw an exception.
          if (empty($this->_primary)) {
              $this->noPrimaryKey = true;
              $col = reset($this->_metadata);
              $this->_primary[1] = $col['COLUMN_NAME'];
              //require_once 'Zend/Db/Table/Exception.php';
              //throw new Zend_Db_Table_Exception('A table must have a primary key, but none was found');
          }
      } else if (!is_array($this->_primary)) {
          $this->_primary = array(1 => $this->_primary);
      } else if (isset($this->_primary[0])) {
          array_unshift($this->_primary, null);
          unset($this->_primary[0]);
      }

      $cols = $this->_getCols();
      if (! array_intersect((array) $this->_primary, $cols) == (array) $this->_primary) {
          require_once 'Zend/Db/Table/Exception.php';
          throw new Zend_Db_Table_Exception("Primary key column(s) ("
              . implode(',', (array) $this->_primary)
              . ") are not columns in this table ("
              . implode(',', $cols)
              . ")");
      }

      $primary    = (array) $this->_primary;
      $pkIdentity = $primary[(int) $this->_identity];

      /**
       * Special case for PostgreSQL: a SERIAL key implicitly uses a sequence
       * object whose name is "<table>_<column>_seq".
       */
      if ($this->_sequence === true && $this->_db instanceof Zend_Db_Adapter_Pdo_Pgsql) {
          $this->_sequence = $this->_db->quoteIdentifier("{$this->_name}_{$pkIdentity}_seq");
          if ($this->_schema) {
              $this->_sequence = $this->_db->quoteIdentifier($this->_schema) . '.' . $this->_sequence;
          }
      }
  }

}
