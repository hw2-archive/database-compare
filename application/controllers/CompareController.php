<?php

class CompareController extends Zend_Controller_Action {

    public function advancedAction() {
        $request = $this->getRequest();
        $id = $request->getParam('id');

        $cache = MyDiff_Cache::init();

        // Create cache and ID if not got one
        if (!$id || (!$comparison = $cache->load('comparison' . $id))) {
            $id = uniqid();
            $databases = $request->getParam('database');

            $comparison = new MyDiff_Comparison;
            foreach ($databases AS $database) {
                $database = new MyDiff_Database($database);
                $database->connect();
                $comparison->addDatabase($database);
            }

            // Add to cache
            $cache->save($comparison, 'comparison' . $id);

            // Reload
            $this->_redirect('compare/advanced/id/' . $id);
        }

        if ($request->isPost()) {
            $options = $request->getParam('options');
            $cache->save($options, 'options' . $id);
            $this->_redirect('compare/run/id/' . $id);
        }

        $this->view->comparison = $comparison;
    }

    public function runAction() {
        $mtime = microtime();
        $mtime = explode(" ", $mtime);
        $mtime = $mtime[1] + $mtime[0];
        $starttime = $mtime;

        $request = $this->getRequest();
        $id = $request->getParam('id');

        $cache = MyDiff_Cache::init();
        $comparison = $cache->load('comparison' . $id);
        $options = $cache->load('options' . $id);

        if (!$id || !$comparison || !$options)
            throw new MyDiff_Exception("Missing options, please go back and try again.");

        // Remove tables not submitted
        foreach ($comparison->databases AS $i => $database) {
            $database->useTables(array_keys($options['database'][$i]['table']));
            $database->connect();
        }

        $upFile = new UpdateFile($comparison,$options);
        // Do compare types
        if (isset($options['type']['schema'])) {
            $comparison->schema();
            $upFile->writeSchema();
        }

        if (isset($options['type']['data'])) {
            $showChanges = isset($options['type']['showchanges']);

            $comparison->data(isset($options['type']['replace']), $options['type']['algorithm'], $upFile, $showChanges);

            if ($showChanges) {
                // Build a list of rows that have changed
                $data = array();
                $tables = array($comparison->databases[0]->getTables(), $comparison->databases[1]->getTables());
                foreach ($tables[0] AS $tableName => $table) {
                    if (!$table->hasDiffs('MyDiff_Diff_Table_New')) {
                        $rows = array($tables[0][$tableName]->getRows(), (isset($tables[1][$tableName]) ? $tables[1][$tableName]->getRows() : array()));

                        // remove values that don't exist in original
                        if (!isset($options['type']['allfields']) && !empty($rows[0]) && !empty($rows[1]))
                            foreach ($rows[1] AS &$row)
                                $row->data = array_intersect_key($row->data, reset($rows[0])->data);

                        $rows = array_merge($rows[0], $rows[1]);
                        $data[] = array('table' => $table, 'rows' => $rows);
                    }
                }

                // fill rows for new tables
                foreach ($tables[1] AS $tableName => $table) {
                    if ($table->hasDiffs('MyDiff_Diff_Table_New')) {
                        $rows = $tables[1][$tableName]->getRows();
                        $data[] = array('table' => $table, 'rows' => $rows);
                    }
                }

                $this->view->data = $data;
            }
        }

        $upFile->closeFile();
        unset($upFile);

        $this->view->comparison = $comparison;
        $this->view->options = $options;

        $mtime = microtime();
        $mtime = explode(" ", $mtime);
        $mtime = $mtime[1] + $mtime[0];
        $endtime = $mtime;
        $totaltime = ($endtime - $starttime);

        $this->view->id = $id;
        $this->view->totaltime = $totaltime;
        $this->view->totalmem = memory_get_peak_usage(true);
    }

}
