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
     * @param $payload
     * @param $queueName
     * @param int $delay
     * @param null $priority
     * @return mixed
     */
    public function push(string $payload, string $queueName, $delay = 0, $priority = NULL);

    /**
     * Pops message from the storage.
     *
     * @param string $queueName
     * @return array|false
     */
    public function pop(string $queueName);

    /**
     * Purge the storage.
     *
     * @param string $queueName
     */
    public function purge(string $queueName);

    /**
     * Release the message.
     * @param string $payload
     * @param string $queueName
     * @param int $delay
     * @return mixed
     */
    public function release(string $payload, string $queueName, $delay = 0);

    /**
     * Delete the message.
     * @param string $queueName
     * @param string|array $payload
     * @return mixed
     */
    public function delete(string $queueName, $payload);

    /**
     * @param string $queueName
     * @param integer $id
     * @return mixed
     */
    public function status(string $queueName, $id = null);

}
