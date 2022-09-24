<?php
class R {

    private static $instance = null;

    private $args = [];

    public static function getInstance() {
        self::init();
        return self::$instance;
    }

    private static function init(){
        if (!self::$instance) {
            self::$instance = new self();
        }
    }

    private function __construct() {

    }

    public static function get($key){
        self::init();
        return self::$instance->args[$key];
    }

    public static function exists($key){
        self::init();
        return in_array($key,self::$instance->args);
    }

    public function set($values){
        $this->args = $values;
    }
}
