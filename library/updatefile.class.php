<?php

class UpdateFile {

    /**
     * Constructor
     *
     */
    function UpdateFile($session,$options) {
        $this->session = $session;
        $this->options = $options;
        $this->myFile = $options['type']['filename'];
        $this->fh = @fopen($this->myFile, 'w'); // suppress warnings
        if ($this->fh != false) {
            $this->cIns = 0; // count insert rows
            $this->cDel = 0; // count delete rows

            $this->tables = array();
            $this->qCollection = array("update" => array(), "delete" => array(), "insert" => array());

            // Connect
            $this->targetdb = $this->session->databases[1];
            $this->sourcedb = $this->session->databases[0];
            $this->s_id = $this->sourcedb->getMysqlConnection();
            $this->t_id = $this->targetdb->getMysqlConnection();
            // store table names
            $this->tables = array($this->sourcedb->getTables(), $this->targetdb->getTables());
        }
    }

    function fillData() {

        if ($this->fh == false && !isset($this->session->options['type']['schema'])
                && !isset($this->session->options['type']['data'])) {
            return false;
        }

        if (!$this->fh) {
            //die("can't open file");
            return false;
        }

        if (isset($this->session->options['type']['data'])) {

            foreach ($this->tables AS $tblId => $table):
                if (!empty($table['rows'])):
                    $pColumns = $table['table']->getPrimaryColumns();
                    $keycnt = count($pColumns);

                    // PROCESSING ROW
                    reset($table['rows']);
                    while (list($key, $row) = each($table['rows'])) {
                        $this->createQuery($row, $pColumns, $keycnt, $table['table']->name);
                        $i++;
                    }
                    $this->writeData($keycnt, $table['table']->name);

                endif;
            endforeach;
        }

        unset($tables);

        return true;
    }

    public function createQuery($row, $pColumns, $keycnt, $tableName) {

        if ($this->fh == false)
                return false;
        
        $keyvalues = array();
        $query = "";
        $diffType = $row->getDiffs();
        $backticks = array( "table" => isset($this->options['type']['backticks_table']) , "field" => isset($this->options['type']['backticks_attribute']),  "filter" => isset($this->options['type']['backticks_filter']));

        // actually we can't collect updates, to implement checking by column
        if ($diffType[0] instanceof MyDiff_Diff_Table_Row_Value) {
            $this->qCollection["update"][] = "UPDATE " . fixTableName($tableName,$backticks['table'],$backticks['filter']) . " SET ";
            // INSERT VALUES PREFIX
        } else if ($diffType[0] instanceof MyDiff_Diff_Table_Row_New) {
            ++$this->cIns;
            if ($this->cIns > 1):
                $this->qCollection["insert"][] = ",\n";
            else:
                $insertPrefix = "INSERT INTO " . fixTableName($tableName,$backticks['table'],$backticks['filter']) . " (";
                $first = true;
                reset($row->data);
                while (list($columnName, $data) = each($row->data)) {
                    if (!$first) {
                        $insertPrefix .= ",";
                    } else {
                        $first = false;
                    }

                    $insertPrefix .= fixFieldName($columnName,$backticks['field'],$backticks['filter']);
                }
                $insertPrefix .= ") VALUES \n";

                $this->qCollection["insert"][] = $insertPrefix;
            endif;

            $this->qCollection["insert"][] = "(";
            // DELETE PREFIX
        } else if ($diffType[0] instanceof MyDiff_Diff_Table_Row_Missing) {
            if ($keycnt == 1) {
                if ($this->cDel == 0)
                    $this->qCollection["delete"][] = "DELETE FROM " . fixTableName($tableName,$backticks['table'],$backticks['filter']) . " WHERE " . $pColumns[1] . " IN (\n";
            } else {
                $this->qCollection["delete"][] = "DELETE FROM " . fixTableName($tableName,$backticks['table'],$backticks['filter']) . " WHERE ";
            }
            ++$this->cDel;
        }

        $dc = 0; // count all fields
        $uc = 0; // count different fields, for update
        $pc = 0; // count primary columns for delete when keycnt > 1
        $totalCols = count($row->data);
        // PROCESSING COLUMNS
        reset($row->data);
        while (list($columnName, $data) = each($row->data)) {
            $isEnd = ++$dc == $totalCols; // first inc then test
            if ($pColumns != null && in_array($columnName, $pColumns)) {
                $keyvalues[] = array($columnName, $data);
            }

            $diff = $row->getValueDiff($columnName);
            if ($diff):
                ++$uc;
                if ($uc > 1):
                    $query .= ", ";
                endif;

                $attribute = fixFieldName($columnName,$backticks['field'],$backticks['filter']);
                if ($diff->compare != null && $diff->compare != "") {
                    $query .= $attribute . "='" . fixMysqlString($diff->compare, $this->t_id) . "'";
                } else {
                    $query .= $attribute . (is_string($diff->compare) ? "=''" : "=(NULL)");
                }

            else:
                // DELETE SYNTAX
                if ($diffType[0] instanceof MyDiff_Diff_Table_Row_Missing) {
                    if ($keycnt == 1 && in_array($columnName, $pColumns)) {
                        if ($this->cDel > 1):
                            $query .= ",\n";
                        endif;

                        $query .= "'" . fixMysqlString($data, $this->t_id) . "'";
                        // MULTI pKEY or NOT PRESENT ( in this case all fields will be used for better deleting )
                    } else if ($keycnt == 0 || ($pColumns && in_array($columnName, $pColumns))) {
                        ++$pc;
                        if ($pc > 1):
                            $query .= " AND ";
                        endif;

                        $attribute = fixFieldName($columnName,$backticks['field'],$backticks['filter']);
                        if ($data != null && $data != "") {
                            $query .= $attribute . "='" . fixMysqlString($data, $this->t_id) . "'";
                        } else {
                            $query .= $attribute . (is_string($data) ? "=''" : " IS NULL");
                        }


                        if (($keycnt == 0 && $isEnd) || $pc == $keycnt):
                            $query .= ";\n";
                        endif;
                    }
                    // INSERT SYNTAX
                } else if ($diffType[0] instanceof MyDiff_Diff_Table_Row_New) {
                    if ($data != null && $data != "") {
                        $query .= "'" . fixMysqlString($data, $this->t_id) . "'";
                    } else {
                        $query .= ( is_string($data) ? "''" : "(NULL)");
                    }

                    if ($isEnd == false):
                        $query .= ",";
                    else:
                        $query .= ")";
                    endif;
                }

            endif;
        }

        if ($diffType[0] instanceof MyDiff_Diff_Table_Row_Missing) {
            $this->qCollection["delete"][] = $query;
        } else if ($diffType[0] instanceof MyDiff_Diff_Table_Row_New) {
            $this->qCollection["insert"][] = $query;
        } else if ($diffType[0] instanceof MyDiff_Diff_Table_Row_Value && $keyvalues != null) {
            $query .= " WHERE ";
            foreach ($keyvalues AS $key) {
                $query .= $key[0] . " = '" . $key[1] . "'";
                if ($key != end($keyvalues)):
                    $query .= " AND ";
                endif;
            }
            $query .= ";\n";

            $this->qCollection["update"][] = $query;
        }
    }

    function writeData($keycnt, $tableName) {
        if ($this->fh == false && empty($this->qCollection["delete"])
                && empty($this->qCollection["insert"]) && empty($this->qCollection["update"])) {
            return false;
        }

        if (!empty($this->qCollection["delete"]) && $keycnt == 1) {
            if ($keycnt == 1) {
                $this->qCollection["delete"][] = ");"; // close braket when delete has 1 pkey
            }
            $this->qCollection["delete"][] = "\n\n";
        }

        if (!empty($this->qCollection["insert"])) {
            $this->qCollection["insert"][] = ";\n\n";
        }

        if (!empty($this->qCollection["update"])) {
            $this->qCollection["update"][] = "\n\n";
        }

        fwrite($this->fh, "\n\n");
        fwrite($this->fh, "####\n");
        fwrite($this->fh, "#### " . $tableName . "\n");
        fwrite($this->fh, "#### \n\n");

        if (!empty($this->qCollection["delete"])) {
            fwrite($this->fh, "#### DELETES\n");
            foreach ($this->qCollection["delete"] AS $collection => $dQueries):
                fwrite($this->fh, $dQueries);
            endforeach;
        }

        if (!empty($this->qCollection["insert"])) {
            fwrite($this->fh, "#### INSERTS\n");
            foreach ($this->qCollection["insert"] AS $collection => $iQueries):
                fwrite($this->fh, $iQueries);
            endforeach;
        }

        if (!empty($this->qCollection["update"])) {
            fwrite($this->fh, "#### UPDATES\n");
            foreach ($this->qCollection["update"] AS $collection => $uQueries):
                fwrite($this->fh, $uQueries);
            endforeach;
        }

        $this->cIns = 0; // count insert rows
        $this->cDel = 0; // count delete rows
        unset($this->qCollection);
    }

    function writeSchema() {
        if ($this->fh == null)
                return false;
        
        $tNames = array();
        $tables = array_merge($this->tables[0], $this->tables[1]);
        $tNames = array_unique(array_keys($tables));

        $schema = generateScript($this->options,$tNames, $this->targetdb, $this->sourcedb);
        if ($schema != ""):
            fwrite($this->fh, $schema . "\n\n\n");
            unset($schema);
        endif;
    }

    function closeFile() {
        if ($this->fh != null)
            fclose($this->fh);
    }

}

?>
