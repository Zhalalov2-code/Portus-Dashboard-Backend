<?php

require_once __DIR__ . '/env.php';

class DB{
    public static $instance;

    public function __construct(){

    }

    public static function getInstance(){
        if (!isset(self::$instance)){
            $host = getenv('DB_HOST') ?: 'localhost';
            $name = getenv('DB_NAME') ?: 'portusapp1';
            $user = getenv('DB_USER');
            $pass = getenv('DB_PASS');

            if ($user === false || $pass === false) {
                throw new \RuntimeException('DB_USER и DB_PASS обязательны — создайте файл .env на основе .env.example');
            }

            self::$instance = new PDO(
                "mysql:host=$host;dbname=$name;charset=utf8mb4",
                $user,
                $pass,
                array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
            );
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_EMPTY_STRING);
        }
        return self::$instance;
    }
}
