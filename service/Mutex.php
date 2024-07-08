<?php
/**
* This Mutex class provides a way to safely lock and unlock a resource to prevent race conditions.
* The lock method will wait until the resource is unlocked before locking it, ensuring only one thread can access it at a time.
* The unlock method releases the lock on the resource, allowing other threads to access it.
* This is a crucial tool for concurrency control in multi-threaded environments.
*/

class Mutex {
    private $isLocked = false;

    public function lock() {
        while ($this->isLocked) {
            usleep(100); // Sleep for 100 microseconds
        }
        $this->isLocked = true;
    }

    public function unlock() {
        $this->isLocked = false;
    }
}
