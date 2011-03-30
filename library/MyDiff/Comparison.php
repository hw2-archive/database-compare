<?php

class MyDiff_Comparison {

    public $databases = array();

    public function addDatabase(MyDiff_Database $database) {
        if (count($this->databases) >= 2)
            throw new MyDiff_Exception('Can only accept two databases');

        $this->databases[] = $database;
    }

    /**
     * Perform a schema comparison on provided databases
     */
    public function schema() {
        $this->isReady();

        // Grab list of tables for each database
        $tables = array($this->databases[0]->getTables(), $this->databases[1]->getTables());

        // Look for differences in number of tables
        $this->doTableDiff($tables);
        // Look for difference in columns
        $this->doTableColumnDiff($tables);
    }

    public function doTableDiff(array $tables) {
        $newTables = array_diff_key($tables[1], $tables[0]);
        $missingTables = array_diff_key($tables[0], $tables[1]);


        foreach ($newTables AS $table) {
            $table->addDiff(new MyDiff_Diff_Table_New);
        }

        foreach ($missingTables AS $table) {
            $table->addDiff(new MyDiff_Diff_Table_Missing);
        }
    }

    public function doTableColumnDiff(array $tables) {
        // Grab tables that are in both
        $matchingTables = array_intersect_key($tables[0], $tables[1]);

        $bar = new ProgressBar('Comparing different table schemas..', true, 0, 400, 40, "#cccccc", "blue", "sdata");
        $bar->initialize(count($matchingTables)); // total number of tables

        foreach ($matchingTables AS $tableName => $table) {
            // Compare schema
            $tableColumns = array(
                0 => $table->getColumns(),
                1 => $tables[1][$tableName]->getColumns(),
            );

            // Look for differences in number of columns
            $newColumns = array_diff_key($tableColumns[0], $tableColumns[1]);
            $missingColumns = array_diff_key($tableColumns[1], $tableColumns[0]);

            // Assign diffs
            foreach ($newColumns AS $column) {
                $column->addDiff(new MyDiff_Diff_Table_Column_New);
            }
            foreach ($missingColumns AS $column) {
                $column->addDiff(new MyDiff_Diff_Table_Column_Missing);
            }

            unset($newColumns, $missingColumns);

            // Remove metadata columns we don't want to compare
            foreach ($tableColumns AS &$columns) {
                foreach ($columns AS &$column)
                    unset($column->metadata['COLUMN_POSITION']);
            }

            // List matching columns
            $matchingColumns = array_intersect_key($tableColumns[0], $tableColumns[1]);

            // Compare each columns metadata
            foreach ($matchingColumns AS $columnName => $column) {
                $differences = array_diff($column->metadata, $tableColumns[1][$columnName]->metadata);
                foreach ($differences AS $metaKey => $metaValue) {
                    $column->addDiff(new MyDiff_Diff_Table_Column_Property($metaKey, $column->metadata[$metaKey]));
                    $tableColumns[1][$columnName]->addDiff(new MyDiff_Diff_Table_Column_Property($metaKey, $tableColumns[1][$columnName]->metadata[$metaKey]));
                }
            }

            unset($matchingColumns, $tableColumns, $columnNames);

            $bar->increase();
        }

        unset($matchingTables, $bar);
    }

    /**
     * Perform a schema comparison on provided databases
     */
    public function data($isReplace, $algorithm, $upFile, $showChanges) {
        $this->isReady();

        // Grab list of tables for each database
        $tables = array($this->databases[0]->getTables(), $this->databases[1]->getTables());

        // Grab tables that are in both
        $matchingTables = array_intersect(array_keys($tables[0]), array_keys($tables[1])); // source - target
        $matchingNewTables = array_diff(array_keys($tables[1]), array_keys($tables[0])); // target - source

        $bar = new ProgressBar('Comparing different table data and fill new tables..', true, 0, 400, 40, "#cccccc", "blue", "cdata");
        $bar->initialize(count($matchingTables) + count($matchingNewTables)); // total number of tables
        // Try to do quick checksum comparison
        foreach ($matchingTables AS $tableName) {

            if ($algorithm == "groupby") {
                if ($this->databases[0]->server != $this->databases[1]->server)
                    die('Actually Cannot execute group by algorithm on different hosts: ' . $this->databases[0]->server . ' - ' . $this->databases[1]->server);

                $this->groupByMethod($tables, $tableName, $isReplace, $upFile, $showChanges);
            } else {
                //processing use only array

                $rows = array($tables[0][$tableName]->getRows(), $tables[1][$tableName]->getRows());
                // Look for new/missing rows
                $newRows = array_diff_key($rows[1], $rows[0]);
                // while approach, to limit the memory consume not copying the array as in foreach
                reset($newRows);
                while (list($key, $value) = each($newRows)) {
                    $this->rowsSetDiff($value, "new");
                }
                unset($newRows);
                $missingRows = array_diff_key($rows[0], $rows[1]);
                reset($missingRows);
                while (list($key, $value) = each($missingRows)) {
                    $this->rowsSetDiff($value, "missing");
                }
                unset($missingRows);
                // Find rows that exist in both
                $compareRows = array_intersect_key($rows[0], $rows[1]);
                reset($compareRows);
                while (list($key, $value) = each($compareRows)) {
                    $this->rowsSetDiff($value, "compare", $rows[1][$key], $isReplace);
                }
                unset($compareRows, $rows);

                $upFile->fillData($this);
            }

            // Prune rows that havent got diffs
            $tables[0][$tableName]->pruneRows();
            $tables[1][$tableName]->pruneRows();

            $bar->increase();
        }

        foreach ($matchingNewTables AS $tableName) {
            // don't use getRows() to avoid memory overload
            $select = $tables[1][$tableName]->getTable()->select()->from($tableName);
            $pColumns = $tables[1][$tableName]->getPrimaryColumns();
            $keycnt = count($pColumns);
            $tables[1][$tableName]->blankRows();
            $stmt = $select->query();
            while ($row = $stmt->fetch()) {
                $cRow = $tables[1][$tableName]->createRow($row, $tables[1][$tableName]->getRowsArray());
                $this->rowsSetDiff($cRow, "new");
                $upFile->createQuery($cRow, $pColumns, $keycnt, $tableName);

                if ($showChanges) {
                    $tables[1][$tableName]->addRow(&$cRow);
                }
            }

            unset($select, $stmt);

            $bar->increase();
        }

        unset($bar, $tables, $tableNames, $matchingTables);
    }

    public function groupByMethod(&$tables, $tableName, $isReplace, $upFile, $showChanges) {
        $fields = "";
        $pKeys = "";
        $IDs = "";
        $selFields = "";
        // check columns exist in both tables
        $columns = array_intersect_key($tables[0][$tableName]->getColumns(), $tables[1][$tableName]->getColumns());
        // count identity,primary and other columns
        $colCnt = array("ID" => 0, "PK" => 0, "CL" => 0, "TOT" => 0);
        foreach ($columns AS $column) {
            if ($column->metadata['IDENTITY']) {
                $IDs .= ( $colCnt["ID"] > 0 ? "," : "") . $column->name;
                ++$colCnt["ID"];
            } else if ($column->metadata['PRIMARY']) {
                $pKeys .= ( $colCnt["PK"] > 0 ? "," : "") . $column->name;
                ++$colCnt["PK"];
            } else {
                $fields .= ( $colCnt["CL"] > 0 ? "," : "") . $column->name;
                ++$colCnt["CL"];
            }

            $selFields .= ( $colCnt["TOT"] > 0 ? "," : "") . $column->name;
            ++$colCnt["TOT"];
        }

        // to convert in zend syntax for other db compatibility
        $sql = "SELECT MIN(tbl_name) AS tbl_name, " . $selFields . "
                FROM
                 (
                  SELECT 'source_table' AS tbl_name , " . $selFields . "
                  FROM " . $this->databases[0]->name . "." . $tableName . " AS S
                  UNION ALL
                  SELECT 'target_table' AS tbl_name , " . $selFields . "
                  FROM " . $this->databases[1]->name . "." . $tableName . " AS D
                )  AS alias_table
                 GROUP BY " . $selFields . "
                 HAVING COUNT(*)=1";

        // to optimize
        if ($IDs != "" || $pKeys != "") {
            $sql .= " ORDER BY ";
            if ($IDs != "" && $pKeys != "") {
                $sql .= $IDs . "," . $pKeys;
            } else if ($IDs != "") {
                $sql .= $IDs;
            } else {
                $sql .= $pKeys;
            }
        }

        $result = mysql_query($sql, $this->databases[0]->getMysqlConnection());
        if (!$result) {
            return false;
        }

        // clean tables
        $tables[0][$tableName]->blankRows();
        $tables[1][$tableName]->blankRows();

        $pColumns = $tables[0][$tableName]->getPrimaryColumns();
        $keycnt = count($pColumns);
        $canCompare = (!empty($pColumns) && (is_array($pColumns)));
        $pKeyData = array();
        $Data = array();
        $num_rows = mysql_num_rows($result);
        // <= to process again after latest row fetch
        if ($num_rows > 0):
            for ($i = 0; $i <= $num_rows; $i++) {
                $row = mysql_fetch_array($result, MYSQL_ASSOC);

                $DataOld = $Data;
                // data (array) - compared (boolean)
                $Data[0] = $row;
                $Data[1] = false;


                if ($canCompare) {
                    $pKeyDataOld = $pKeyData;
                    unset($pKeyData); //reset
                    foreach ($pColumns AS $pkName) {
                        $pKeyData[] = $row[$pkName];
                    }
                }

                //hack code, to optimize ?
                //comparing with old value, it is possible with "order by"
                if ($canCompare && $pKeyDataOld == $pKeyData) {
                    if ($Data[0]['tbl_name'] == $DataOld[0]['tbl_name']) {
                        continue; // can't happen
                    }

                    if ($Data[0]['tbl_name'] == 'source_table') {
                        $values = array_remove_keys($Data[0], 'tbl_name');
                        $compare = array_remove_keys($DataOld[0], 'tbl_name');
                    } else {
                        $values = array_remove_keys($DataOld[0], 'tbl_name');
                        $compare = array_remove_keys($Data[0], 'tbl_name');
                    }
                    $cRow = $tables[0][$tableName]->createRow($values, $tables[0][$tableName]->getRowsArray());
                    //create query using target data
                    $this->rowsSetDiff($cRow, "compare", $compare, $isReplace);
                    // in create query we get the compare value from source row
                    $upFile->createQuery($cRow, $pColumns, $keycnt, $tableName);
                    if ($showChanges) {
                        $tables[0][$tableName]->addRow($cRow);
                    }
                    // compared
                    $DataOld[1] = true;
                    $Data[1] = true;
                } else if ($i > 0 && $DataOld[1] == false) {
                    if ($DataOld[0]['tbl_name'] == "source_table") {
                        $cRow = $tables[0][$tableName]->createRow(array_remove_keys($DataOld[0], 'tbl_name'), $tables[0][$tableName]->getRowsArray());
                        $this->rowsSetDiff($cRow, "missing");
                        $upFile->createQuery($cRow, $pColumns, $keycnt, $tableName);
                        if ($showChanges) {
                            $tables[0][$tableName]->addRow($cRow);
                        }
                    } else if ($DataOld[0]['tbl_name'] == "target_table") {
                        $cRow = $tables[1][$tableName]->createRow(array_remove_keys($DataOld[0], 'tbl_name'), $tables[1][$tableName]->getRowsArray());
                        $this->rowsSetDiff($cRow, "new");
                        $upFile->createQuery($cRow, $pColumns, $keycnt, $tableName);
                        if ($showChanges) {
                            $tables[1][$tableName]->addRow($cRow);
                        }
                    }
                }
            }

            $upFile->writeData($keycnt, $tableName);
        endif;
    }

    public function rowsSetDiff($row, $option, $compare = null, $isReplace = null) {
        // Assign diffs
        if ($option == "new") {
            $row->addDiff(new MyDiff_Diff_Table_Row_New);
        } else if ($option == "missing") {
            $row->addDiff(new MyDiff_Diff_Table_Row_Missing);
        } else if ($option == "compare") {
            // Only compare if both are using primary keys
            if ($row->hasPrimary()) {

                if (!$isReplace) {

                    $differences = array_diff($row->data, $compare);
                    foreach ($differences AS $key => $value) {
                        $row->addDiff(new MyDiff_Diff_Table_Row_Value($key, $row->data[$key], $compare[$key]));
                    }
                    unset($differences);
                } else {
                    // to implement
                    //$row->addDiff(new MyDiff_Diff_Table_Row_Value());
                }
            }
        }

        unset($row);
    }

    public function isReady() {
        if (count($this->databases) !== 2)
            throw new MyDiff_Exception('Must provide two databases');

        set_time_limit(0); // no limit, suggested : set_time_limit(600);
        ini_set('memory_limit', '256M');

        return true;
    }

}
