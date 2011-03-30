<?php

require_once (dirname(__FILE__) . '/../../bootstrap.php');

class Library_MyDiff_ComparisonTest extends PHPUnit_Framework_TestCase {

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testAddDatabaseThrowsErrorIfNotGivenDiffDb() {
        $comparison = new MyDiff_Comparison();

        $class = new stdClass();
        $comparison->addDatabase($class);
    }

    /**
     * @expectedException MyDiff_Exception
     */
    public function testAddDatabaseThrowsExceptionIfMoreThanTwoDatabases() {
        $comparison = new MyDiff_Comparison();

        $class = new MyDiff_Database();
        $comparison->addDatabase($class);
        $comparison->addDatabase($class);
        $comparison->addDatabase($class);
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testDoTableDiffThrowsErrorIfNotGivenArray() {
        $comparison = new MyDiff_Comparison();
        $comparison->doTableDiff(null);
    }

    public function testDoTableDiffFindsNewTable() {
        $comparison = new MyDiff_Comparison();
        $table = $this->getMock('MyDiff_Table', array('addDiff'), array(null, 'tester'));

        $tableSetOne = array();
        $tableSetTwo = array('tester' => $table);
        $tables = array($tableSetOne, $tableSetTwo);

        $table->expects($this->once())
                ->method('addDiff')
                ->with($this->isInstanceOf('MyDiff_Diff_Table_New'));

        $comparison->doTableDiff($tables);
    }

    public function testDoTableDiffFindsMissingTable() {
        $comparison = new MyDiff_Comparison();
        $table = $this->getMock('MyDiff_Table', array('addDiff'), array(null, 'tester'));

        $tableSetOne = array('tester' => $table);
        $tableSetTwo = array();
        $tables = array($tableSetOne, $tableSetTwo);

        $table->expects($this->once())
                ->method('addDiff')
                ->with($this->isInstanceOf('MyDiff_Diff_Table_Missing'));

        $comparison->doTableDiff($tables);
    }

}
