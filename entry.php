<?php

define('APP_PATH', dirname(__FILE__));
require_once APP_PATH . '/library/hello.php';
require_once APP_PATH . '/library/functions.php';
require_once APP_PATH . '/app/main.php';
if(file_exists(APP_PATH . '/vendor/autoload.php'))
    require_once APP_PATH . '/vendor/autoload.php';
spl_autoload_register('autoLoadEntry',true,true);
try {
    $registry = R::getInstance();
    $registry->set($argv);
    $app = new main(APP_PATH . '/conf/config.ini');
    $app->bootstrap()->init()->run();
} catch (Exception $e){
    echo 'Error:'.$e->getMessage().PHP_EOL;
}
