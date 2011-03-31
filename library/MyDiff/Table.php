<?php

class MyDiff_Table extends MyDiff_Item {

    public $name;
    protected $_database;
    protected $_table;
    protected $_columns;
    protected $_primaryColumns;
    protected $_rows;
    protected $_engine;

    public function __construct($db, $tableName) {
        $this->name = $tableName;
        $this->_database = $db;
    }

    public function getTable() {
        if ($this->_table === null) {
            $this->_table = new MyDiff_Db_Table(array('name' => $this->name, 'db' => $this->_database->getDb()));
        }

        return $this->_table;
    }

    /**
     * Get list of columns and their metadata
     */
    public function getColumns() {
        if ($this->_columns === null) {
            $columns = array();
            $metadata = $this->getTable()->info(Zend_Db_Table_Abstract::METADATA);
            foreach ($metadata AS $columnName => $columnMetaData) {
                $columns[$columnName] = new MyDiff_Table_Column($columnMetaData);
            }

            $this->_columns = $columns;
            unset($columns, $metadata);
        }

        return $this->_columns;
    }

    /**
     * Get array of primary keys (column names)
     */
    public function getPrimaryColumns() {
        if ($this->_primaryColumns === null && !$this->getTable()->noPrimaryKey) {
            $this->_primaryColumns = $this->getTable()->info(Zend_Db_Table_Abstract::PRIMARY);
        }

        return $this->_primaryColumns;
    }

    /**
     * Get the table's mysql storage engine type
     */
    public function getEngine() {
        if ($this->_engine === null) {
            $select = $this->getTable()->select();
            $select->setIntegrityCheck(false);
            $select->from('information_schema.TABLES', 'ENGINE')
                    ->where('TABLE_SCHEMA = ?', $this->_database->name)
                    ->where('TABLE_NAME = ?', $this->name);


            $row = $this->getTable()->fetchRow($select);
            $this->_engine = isset($row['ENGINE']) ? $row['ENGINE'] : null;
        }

        return $this->_engine;
    }

    public function getChecksum() {
        if ($this->getEngine() == 'MyISAM') {
            $stmt = $this->_database->getDb()->query('CHECKSUM TABLE `' . $this->name . '`');
            $result = $stmt->fetch();
            return isset($result['Checksum']) ? $result['Checksum'] : null;
        } else {
            return null;
        }
    }

    /**
     * Get the table rows (i.e. data)
     */
    public function getRows() {
        if ($this->_rows === null) {
            $rows = array();
            $dbRows = $this->getTable()->fetchAll()->toArray();
            foreach ($dbRows AS $dbRow) {
                $row = $this->createRow($dbRow, $rows);
                $rows[$row->uid] = $row;
            }

            $this->_rows = $rows;
            unset($rows, $dbRows);
        }

        return $this->_rows;
    }

    /**
     * Create row and return it
     */
    public function createRow($data, &$rows) {
        if ($data != null) {
            $row = new MyDiff_Table_Row($this, $data);

            // check uid is unique
            // useful for data comparisons where we might have duplicates not using primary keys
            $i = 0;
            $keys = array_keys($rows);
            while (in_array($row->uid, $keys)) {
                if ($i > 1000)
                    throw new Exception('Found over 1000 duplicate rows');
                $row->uid = md5($row->uid . $i);
                $i++;
            }
            unset($keys, $data);

            return $row;
        }
        return null;
    }

    public function setRows($rows) {
        unset($this->_rows);
        $this->_rows = $rows;
    }

    public function getRowsArray() {
        return $this->_rows;
    }

    public function addRow($row) {
        $this->_rows[$row->uid] = $row;
    }

    public function getRowById($uid) {
        return $this->_rows[$uid];
    }

    /**
     * Remove data from rows that don't have any differences
     */
    public function pruneRows() {
        if (!empty($this->_rows)) {
            reset($this->_rows);
            while (list($rowId, $row) = each($this->_rows)) {
                if (!$row->hasDiffs())
                    unset($this->_rows[$rowId]);
            }
        }
    }

    /**
     * Set rows to blank, because diff now required
     */
    public function blankRows() {
        unset($this->_rows);
        $this->_rows = array();
    }

    /**
     * Override getDiffs for column diffs too
     */
    public function getDiffs($type = null) {
        $allDiffs = parent::getDiffs($type);
        foreach ($this->getColumns() AS $column) {
            $allDiffs = array_merge($allDiffs, $column->getDiffs($type));
        }

        return $allDiffs;
    }

}
