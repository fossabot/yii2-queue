<?php

namespace mirocow\queue\drivers;

use mirocow\queue\drivers\common\BaseConnection;
use yii\base\NotSupportedException;
use \yii\redis\Connection;

/**
 * RedisConnection
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class RedisConnection extends BaseConnection
{

    /** @var Connection null  */
    public $connection = null;

    /**
     * @var int
     */
    private $now = 0;

    function init()
    {
        parent::init();

        $this->connection = \yii\di\Instance::ensure(
          \Yii::$app->{$this->connection},
          Connection::className()
        );
    }

    /**
     * @param array $message
     */
	public function delete(string $queueName, string $payload)
	{
        $this->connection->zrem($queueName . '.messages', $payload);
	}

    /**
     * @param $queueName
     * @param int $timeout
     * @return array|null
     */
	public function pop(string $queueName)
	{
        // Move delayed messages into waiting
        if ($this->now < time()) {
            $this->now = time();
            if ($delayed = $this->connection->zrevrangebyscore($queueName.'.delayed', $this->now, '-inf')) {
                $this->connection->zremrangebyscore($queueName.'.delayed', '-inf', $this->now);
                foreach ($delayed as $id) {
                    if(!is_numeric($id)) continue;
                    $this->connection->rpush($queueName.'.waiting', $id);
                }
            }
        }

        // Find a new waiting message
        if (!$this->timeout) {
            if ($id = $this->connection->rpop($queueName.'.waiting')) {
                if(is_numeric($id)) {
                    $message = $this->connection->hget($queueName . '.messages', $id);
                    $this->connection->hdel("{$queueName}.messages", $id);
                    return $message;
                }
            }
        } else {
            if ($result = $this->connection->brpop("{$queueName}.waiting", $this->timeout)) {
                if(count($result) == 2 && is_numeric($result[1])) {
                    $id = $result[1];
                    $message = $this->connection->hget("{$queueName}.messages", $id);
                    $this->connection->hdel("{$queueName}.messages", $id);
                    return $message;
                }
            }
        }

        return null;
	}

    /**
     * @param string $queueName
     */
	public function purge(string $queueName)
	{
        $this->connection->del([$queueName, $queueName . '.delayed', $queueName . '.reserved', $queueName.'.messages']);
	}

    /**
     * @param mixed $message
     * @param $queueName
     * @param int $delay
     * @return mixed
     */
	public function push(string $payload, string $queueName, $delay = 0, $priority = NULL)
	{
        if ($priority !== null) {
            throw new NotSupportedException('Job priority is not supported in the driver.');
        }
        $id = $this->connection->incr($queueName.'.message_id');
        if (!$delay) {
            $this->connection->lpush($queueName.'.waiting', $id);
            $this->connection->hset($queueName.'.messages', $id, $payload);
        } else {
            $this->connection->zadd($queueName.'.delayed', time() + $delay, $id);
            $this->connection->hset($queueName.'.messages', $id, $payload);
        }
        return $id;
	}

    /**
     * @param array $message
     * @param int $delay
     */
	public function release(string $payload, string $queueName, $delay = 0)
	{
        $id = $this->connection->hget($queueName.'.message_id');

        if ($delay > 0) {
            $this->connection->zadd($queueName.'.delayed', time() + $delay, $id);
        } else {
            $this->connection->rpush($queueName.'.messages', $payload);
        }
	}

    /**
     * @param string $queueName
     * @param null $id
     * @return int
     */
    public function status(string $queueName, $id = null){
	    if($id) {
            if (!is_numeric($id) || $id <= 0) {
                throw new InvalidParamException("Unknown messages ID: $id.");
            }
            if ($this->connection->hexists($queueName.'messages', $id)) {
                return self::STATUS_WAITING;
            }
            else {
                return self::STATUS_DONE;
            }
        } else {
	        $waiting = $this->getWaitingCount($queueName);
	        $delayed = $this->getDelayedCount($queueName);
            $done = $this->getDoneCount($queueName);

	        echo "Queue name: {$queueName}\nWaiting: {$waiting}\nDelayed: {$delayed}\nDone: {$done}\n\n";
        }
    }

    /**
     * @return integer
     */
    protected function getWaitingCount(string $queueName)
    {
        return $this->connection->llen($queueName . '.waiting');
    }

    /**
     * @return integer
     */
    protected function getDelayedCount(string $queueName)
    {
        return $this->connection->zcount($queueName . '.delayed', '-inf', '+inf');
    }

    /**
     * @return integer
     */
    protected function getDoneCount(string $queueName)
    {
        $total = $this->connection->get($queueName . '.message_id');
        $done = $total - $this->getDelayedCount() - $this->getWaitingCount();
        return $done;
    }
}
