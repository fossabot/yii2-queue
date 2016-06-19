<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\queue\interfaces;

/**
 * Driver inteface
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
interface DriverInterface
{


	/**
	 * Push payload to the storage.
	 *
	 * @param mixed $payload
	 * @param integer $delay
	 * @param string $queue
	 * @return string
	 */
	public function push($payload, $queue, $delay = 0);

	/**
	 * Pops message from the storage.
	 *
	 * @param string $queue
	 * @return array|false
	 */
	public function pop($queue);

	/**
	 * Purge the storage.
	 *
	 * @param string $queue
	 */
	public function purge($queue);

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
