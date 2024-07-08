<?php
/**
 * This class MemoryCache provides functionality to store key-value pairs in memory with options for expiration, item count limit, and total memory limit.
 * It uses a Mutex for thread safety and ensures that the memory limits are not exceeded by removing items when necessary.
 * The set method allows you to add new items to the cache while managing memory limits and expiration times.
 * The get method retrieves a value from the cache if it exists and is not expired.
 * Overall, this class offers a flexible and efficient way to manage cached data in memory.
 *
 * @param {boolean} expirable flag - Flag to specify if data should be expired.
 * @param {Integer} itemCountLimit - Total limit count of data. If 0, no limit.
 * @param {Integer} totalMemoryMBLimit - Total memory limit in bytes. If 0, no limit.
 * 
 * USAGE:
 * $cache = new MemoryCache(TRUE, 100, 10);
 * $cache = MemoryCache::getInstance(TRUE, 100, 10);
 * 
 * $value = $cache->get($key);
 * if ($value) {
 *      ...
 * }
 * $cache->set($key, $value, $seconds);
 */

require_once 'Mutex.php';

class MemoryCache {
    private static $instance = NULL;

    private $cache = [];
    private $expireTimes = [];

    private $mutex;

    private $expirable = TRUE;
    private $itemCountLimit = 0;// if 0, no limit
    private $totalMemoryLimit = 0;// if 0, no limit, else in Bytes
    private $currentMemoryUsage = 0;

    public function __construct($expirable = TRUE, $itemCountLimit = 100, $totalMemoryMBLimit = 100) {
        $this->expirable = $expirable;
        $this->itemCountLimit = $itemCountLimit;
        $this->totalMemoryLimit = $totalMemoryMBLimit * 1024 *1024;
        $this->mutex = new Mutex();

        self::$instance = $this;
    }

    public static function getInstance($expirable, $itemCountLimit, $totalMemoryLimit) {
        if (!isset(self::$instance)) {
            self::$instance = new self($expirable, $itemCountLimit, $totalMemoryLimit);
        }
        return self::$instance;
    }

    public function set($key, $value, $expireInSeconds = null) {
        if ($key && $value) {
            $this->mutex->lock();
            
            if ($this->expirable)
                $this->cleanExpiredItems();

            if ($this->totalMemoryLimit > 0) {
                // Convert memory usage to bytes for comparison
                $valueSize = strlen(serialize($value));

                // Check if adding this item exceeds memory limit
                if ($this->currentMemoryUsage + $valueSize > $this->totalMemoryLimit) {
                    $this->removeFirstItem();
                }
                $this->currentMemoryUsage += $valueSize;
            }

            if ($this->itemCountLimit > 0 && count($this->cache) > $this->itemCountLimit) {
                $this->removeFirstItem();
            }

            $this->cache[$key] = $value;

            if ($this->expirable) {
                $this->expireTimes[$key] = $expireInSeconds ? time() + $expireInSeconds : null;
            }

            $this->mutex->unlock();
        }
    }

    public function get($key) {
        $ret = false;

        $this->mutex->lock();

        if ($key && isset($this->cache[$key])) {
            if ($this->isExpired($key)) {
                $this->delete($key);
            } else {
                $ret = $this->cache[$key];
            }
        }

        $this->mutex->unlock();

        return $ret;
    }

    protected function delete($key) {
        if ($key && isset($this->cache[$key])) {
            if ($this->totalMemoryLimit > 0) {
                $valueSize = strlen(serialize($this->cache[$key]));
                $this->currentMemoryUsage -= $valueSize;
            }
            unset($this->cache[$key]);
            if ($this->expirable)
                unset($this->expireTimes[$key]);
        }
    }

    protected function cleanExpiredItems() {
        foreach ($this->cache as $key => $value) {
            if ($this->isExpired($key)) {
                $this->delete($key);
            }
        }
    }

    protected function isExpired($key) {
        if ($this->expirable && 
            isset($this->expireTimes[$key]) && $this->expireTimes[$key] !== null) {
            return $this->expireTimes[$key] < time();
        }
        return false;
    }

    // Removes the first item from the cache.
    protected function removeFirstItem() {
        // Move the internal pointer of an array to its first element and returns the value of the first array element, or false if the array is empty.
        reset($this->cache);

        $firstKey = key($this->cache);
        if ($firstKey)
            $this->delete($firstKey);
    }
}
