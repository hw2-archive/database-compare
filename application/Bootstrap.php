<?php

require_once "Zend/Application/Bootstrap/Bootstrap.php";

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
  /**
   * Set up autoloading
   */
  public function _initAutoloader()
  {
    require_once "Zend/Loader/Autoloader.php";
    $autoloader = Zend_Loader_Autoloader::getInstance();
    $autoloader->registerNamespace('MyDiff_');
  }

  public function _initDatabase()
  {
    // Register DB
    $dbConfig = new Zend_Config_Ini(
      APPLICATION_PATH . '/configs/database.ini',
      APPLICATION_ENV
    );

    $dbAdapter = Zend_Db::factory($dbConfig->database);
    Zend_Db_Table_Abstract::setDefaultAdapter($dbAdapter);
    Zend_Registry::set('dbAdapter', $dbAdapter);
  }
}
