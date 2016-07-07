<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\queue\interfaces;
use yii\queue\models\MessageModel;

/**
 * Channel inteface
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
interface ChannelInterface
{

    /**
     * Create channel
     *
     * @param array $options
     */
    public function create(array $options);

    /**
     * Open channel connection
     */
    public function open();

    /**
     * Close channel connection
     */
    public function close();

    /**
     * Send data to channel connection
     *
     * @param string $name
     * @param mixed $data
     */
    public function send($name, $data);

    /**
     * Push message to the queue.
     *
     * @param mixed $message
     * @param integer $delay
     * @return string
     */
    public function push(MessageModel $message, $delay = 0);

    /**
     * Pops message from the queue.
     *
     * @return array|false
     */
    public function pop();

    /**
     * Purge the queue.
     *
     */
    public function purge();

    /**
     * Release the message.
     *
     * @param array $message
     * @param integer $delay
     */
    public function release(array $message, $delay = 0);

    /**
     * Delete the message.
     *
     * @param array $message
     */
    public function delete(array $message);
}
