<?php

class IndexController extends Zend_Controller_Action
{
    public function indexAction()
    {
			$types = array(
				'PDO_MYSQL' => 'MySQL (PDO)',
                                // to implement
				/*'MYSQLI' => 'MySQLi',
				'PDO_MSSQL' => 'MsSQL [untested]',
				'PDO_SQLITE' => 'Sql Lite [untested]',
				'ORACLE' => 'Oracle', */
			);
			
			$this->view->types = $types;
	}


}
