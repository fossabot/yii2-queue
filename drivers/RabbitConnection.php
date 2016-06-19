<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\queue\drivers;

use \yii\queue\interfaces\DriverInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use yii\helpers\Json;

/**
 * RabbitConnection
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class RabbitConnection extends \yii\base\Component implements DriverInterface
{
    /**
     * @var string
     */
    public $host = '';
    /**
     * @var int
     */
    public $port = 5672;
    /**
     * @var
     */
    public $user = '';
    /**
     * @var string
     */
    public $password = '';
    /**
     * @var string
     */
    public $vhost = '/';

    /**
     * @var AMQPStreamConnection
     */
    private $_connection;
    /**
     * @var AMQPChannel
     */
    private $_channel;
    /**
     * @var array
     */
    private $_queues = [];
    /**
     * @var array
     */
    public $exchangeSettings = [];
    /**
     * @var array
     */
    public $queueSettings = [];

    /**
     *
     */
    public function close()
    {
        if ($this->_channel !== null) {
            $this->_channel->close();
            $this->_channel = null;
        }
        if ($this->_connection !== null) {
            $this->_connection->close();
            $this->_connection = null;
        }
        return is_null($this->_channel) && is_null($this->_connection);
    }

    /**
     * @return AMQPStreamConnection
     */
    private function getConnection()
    {
        if (!isset($this->_connection)) {
            $this->_connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password, $this->vhost);
        }
        return $this->_connection;
    }

    /**
     * @return \PhpAmqpLib\Channel\AMQPChannel
     */
    private function getChannel()
    {
        if (!isset($this->_channel)) {
            $this->_channel = $this->getConnection()->channel();
        }
        return $this->_channel;
    }

    /**
     * @param $queue
     * @return bool
     */
    private function declareQueue($queue)
    {
        $settings = isset($this->queueSettings[$queue]) ? $this->queueSettings[$queue] : [];

        $passive = isset($settings['passive']) ? $settings['passive'] : false;
        $durable = isset($settings['durable']) ? $settings['durable'] : true;
        $exclusive = isset($settings['exclusive']) ? $settings['exclusive'] : false;
        $autoDelete = isset($settings['auto_delete']) ? $settings['auto_delete'] : false;
        $nowait = isset($settings['nowait']) ? $settings['nowait'] : false;
        $arguments = isset($settings['arguments']) ? $settings['arguments'] : null;
        $ticket = isset($settings['ticket']) ? $settings['ticket'] : null;

        return $this->getChannel()->queue_declare($queue, $passive, $durable, $exclusive, $autoDelete, $nowait, $arguments, $ticket);
    }

    /**
     * @param $name
     * @return bool
     */
    private function declareExchange($name)
    {
        $settings = isset($this->exchangeSettings[$name]) ? $this->exchangeSettings[$name] : [];

        $type = isset($settings['type']) ? $settings['type'] : 'direct';
        $passive = isset($settings['passive']) ? $settings['passive'] : false;
        $durable = isset($settings['durable']) ? $settings['durable'] : true;
        $autoDelete = isset($settings['auto_delete']) ? $settings['auto_delete'] : false;
        $internal = isset($settings['internal']) ? $settings['internal'] : false;
        $nowait = isset($settings['nowait']) ? $settings['nowait'] : false;
        $arguments = isset($settings['arguments']) ? $settings['arguments'] : null;
        $ticket = isset($settings['ticket']) ? $settings['ticket'] : null;

        return $this->getChannel()->exchange_declare($name, $type, $passive, $durable, $autoDelete, $internal, $nowait, $arguments, $ticket);
    }

    /**
     * @param string $queue
     * @param string $exchange
     * @return mixed
     */
    private function bindQueue($queue, $exchange)
    {
        return $this->getChannel()->queue_bind($queue, $exchange);
    }

    /**
     * @param string $queue
     * @return bool
     */
    private function prepareQueue($queue)
    {
        if (!isset($this->_queues[$queue])) {
            $this->declareQueue($queue);
            $this->declareExchange($queue);
            $this->bindQueue($queue, $queue);
            $this->_queues[$queue] = true;
            return true;
        } else
            return false;
    }

    /**
     * Push payload to the queue.
     *
     * @param mixed $payload
     * @param integer $delay
     * @param string $queue
     * @return string
     */
    public function push($payload, $queue, $delay = 0)
    {
        $payload = Json::encode(['id' => $id = md5(uniqid('', true)), 'body' => serialize($payload)]);
        $this->prepareQueue($queue);
        $message = new AMQPMessage($payload);
        $this->getChannel()->basic_publish($message, $queue);
        return $id;
    }

    /**
     * Pops message from the queue.
     *
     * @param string $queue
     * @return array|false
     */
    public function pop($queue)
    {
        $this->prepareQueue($queue);
        $message = $this->getChannel()->basic_get($queue);
        if (!$message) {
            return false;
        }
        $payload = Json::decode($message->body);
        return [
            'id' => $payload['id'],
            'body' => unserialize($payload['body']),
            'queue' => $queue,
            'delivery_tag' => $message->delivery_info['delivery_tag'],
        ];
    }

    /**
     * Purges the queue.
     *
     * @param string $queue
     */
    public function purge($queue)
    {
        $this->getChannel()->queue_delete($queue);
        $this->getChannel()->exchange_delete($queue);
    }

    /**
     * Releases the message.
     *
     * @param array $message
     * @param integer $delay
     * @throws Exception
     */
    public function release(array $message, $delay = 0)
    {
        throw new Exception("Not implemented");
    }

    /**
     * Deletes the message.
     *
     * @param array $message
     */
    public function delete(array $message)
    {
        $this->getChannel()->basic_ack($message['delivery_tag']);
    }

}
