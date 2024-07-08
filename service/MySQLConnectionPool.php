<?php
/**
 * This class represents a MySQL connection pool that allows multiple connections to be managed and reused efficiently.
 * It ensures that a limited number of connections are created at any given time and that idle connections are reused.
 * The pool is thread-safe thanks to the Mutex class being used to manage access to the pool.
 * 
 * USAGE:
 * $pool = new MySQLConnectionPool(10);
 * $pool = MySQLConnectionPool::getInstance(10);
 * 
 * $conn = $pool->getConnection();
 * if ($conn) {
 *      ...
 *      $pool->releaseConnection($conn);
 * }
 */
require_once 'Mutex.php';

class MySQLConnectionPool {
    private static $instance = NULL;

    private $pool = [];
    private $mutex;

    private $maxConnections = 10;

    private $servername = "localhost";
    private $username = "root";
    private $password = "";
    private $dbname = "colnect";

    public function __construct($maxConnections = 10) {
        $this->maxConnections = $maxConnections;
        $this->initializePool();

        $this->mutex = new Mutex();

        self::$instance = $this;
    }

    public static function getInstance($maxConnections) {
        if (!isset(self::$instance)) {
            self::$instance = new self($maxConnections);
        }
        return self::$instance;
    }

    private function initializePool() {
        for ($i = 0; $i < $this->maxConnections; $i++) {
            $this->pool[] = $this->createConnection();
        }
    }

    private function createConnection() {
        $connection = new mysqli($this->servername, $this->username, $this->password, $this->dbname);
        if ($connection->connect_error) {
            die("Connection failed: " . $connection->connect_error);
        }
        return $connection;
    }
    
    private function isConnectionAlive($connection) {
        if ($connection->ping()) {
            return true;
        } else {
            $connection->close();
            return false;
        }
    }

    public function getConnection() {
        $conn = null;

        $this->mutex->lock();

        while (count($this->pool) === 0) {
            // Wait until a connection becomes available
            $this->mutex->unlock();
            usleep(100000); // 100ms
            $this->mutex->lock();
        }

        $conn = array_pop($this->pool);
        if (!$this->isConnectionAlive($conn)) {
            $conn = $this->createConnection();
        }

        $this->mutex->unlock();

        return $conn;
    }

    public function releaseConnection($connection) {
        $this->mutex->lock();

        $this->pool[] = $connection;

        $this->mutex->unlock();
    }

    public function closeAllConnections() {
        $this->mutex->lock();

        foreach ($this->pool as $connection) {
            $connection->close();
        }
        $this->pool = [];

        $this->mutex->unlock();
    }
}