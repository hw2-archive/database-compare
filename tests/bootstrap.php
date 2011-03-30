<?php

define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));
define('PUBLIC_PATH', realpath(dirname(__FILE__) . '/../public'));

defined('APPLICATION_ENV')
        || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'testing'));

set_include_path(
        APPLICATION_PATH . '/../library/'
        . PATH_SEPARATOR
        . APPLICATION_PATH . '/classes/'
        . PATH_SEPARATOR . get_include_path()
);

try {
    /** Zend_Application */
    require_once 'Zend/Application.php';

// Create application, bootstrap, and run
    $application = new Zend_Application(
                    APPLICATION_ENV,
                    APPLICATION_PATH . '/configs/application.ini'
    );
    $application->bootstrap();
} catch (Exception $exception) {
    echo 'An exception occured while bootstrapping the application.';
    if (defined('APPLICATION_ENV')
            && APPLICATION_ENV != 'production'
    ) {
        echo '<br /><br />' . $exception->getMessage() . '<br />'
        . '<div align="left">Stack Trace:'
        . '<pre>' . $exception->getTraceAsString() . '</pre></div>';
    }
    exit(1);
}