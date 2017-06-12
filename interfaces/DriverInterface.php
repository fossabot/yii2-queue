<?php

namespace mirocow\queue\interfaces;

/**
 * Driver inteface
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
interface DriverInterface
{

    /**
     * Push payload to the storage.
     * @param $message
     * @param $queueName
     * @param int $delay
     * @param null $priority
     * @return mixed
     */
    public function push(string $payload, string $queueName, $delay = 0, $priority = NULL);

    /**
     * Pops message from the storage.
     *
     * @param string $queue
     * @return array|false
     */
    public function pop(string $queueName);

    /**
     * Purge the storage.
     *
     * @param string $queue
     */
    public function purge(string $queueName);

    /**
     * Release the message.
     *
     * @param array $message
     * @param integer $delay
     */
    public function release(string $payload, string $queueName, $delay = 0);

    /**
     * Delete the message.
     * @param string $queueName
     * @param string $payload
     * @return mixed
     */
    public function delete(string $queueName, string $payload);

    /**
     * @param string $queueName
     * @return mixed
     */
    public function status(string $queueName);

}
