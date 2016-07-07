<?php
/**
 * Created by PhpStorm.
 * User: Nikolay
 * Date: 29.06.2016
 * Time: 15:46
 */

namespace yii\queue\components;


use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\queue\exceptions\ChannelException;
use yii\queue\interfaces\ChannelInterface;
use yii\queue\models\MessageModel;

/**
 * Class BaseQueueChannel
 * @package yii\queue
 *
 * @property $driver \yii\queue\interfaces\DriverInterface
 */
class ChannelComponent extends Component implements ChannelInterface
{

    public $driver;
    public $queueName;
    public $channelName = "default";

    public function init()
    {
        parent::init();

        if (is_array($this->driver) || class_exists($this->driver)) {
            $this->driver = \Yii::createObject($this->driver);
        } elseif (\Yii::$app->has($this->driver)) {
            $this->driver = \yii\di\Instance::ensure(
                \Yii::$app->{$this->driver}
            );
        } else
            throw new InvalidConfigException;
    }

    public function getQueueName($queue = null)
    {
        return ($queue ?: $this->queueName) . ':' . $this->channelName;
    }

    /**
     * @param array $options
     * @return ChannelComponent
     * @throws InvalidConfigException
     */
    public function create(array $options)
    {
        try {
            return new self(\Yii::createObject($options));
        } catch (ChannelException $cex) {
            throw new $cex;
        }
    }

    /**
     * Open channel connection
     */
    public function open()
    {

    }

    /**
     * Close channel connection
     */
    public function close()
    {

    }

    /**
     * Send data to channel connection
     *
     * @param string $name
     * @param mixed $data
     */
    public function send($name, $data)
    {

    }

    /**
     * @param MessageModel $message
     * @param int $delay
     * @return mixed
     * @throws ChannelException
     */
    public function push(MessageModel $message, $delay = 0)
    {
        if ($message instanceof MessageModel) {
            if ($message->validate()) {
                return $this->driver->push($message->toJSON(), $this->getQueueName(), $delay);
            } else {
                throw new ChannelException("message is not valid MessageModel");
            }
        } else {
            throw new ChannelException("message is not instanceof MessageModel");
        }
    }

    /**
     * Pops message from the queue.
     *
     * @return MessageModel|null
     */
    public function pop()
    {
        $message = null;
        if ($rawMessage = $this->driver->pop($this->getQueueName())) {
            $message = MessageModel::loadRawMessage($rawMessage);
        }
        return $message;
    }

    /**
     * Purge the queue.
     *
     */
    public function purge()
    {
        return $this->driver->purge($this->getQueueName());
    }

    /**
     * Release the message.
     *
     * @param array $message
     * @param integer $delay
     */
    public function release(array $message, $delay = 0)
    {
        return $this->driver->release($message, $delay);
    }

    /**
     * Delete the message.
     *
     * @param array $message
     */
    public function delete(array $message)
    {
        return $this->driver->delete($message);
    }
}