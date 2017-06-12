<?php


namespace mirocow\queue\components;


use mirocow\queue\exceptions\ChannelException;
use mirocow\queue\interfaces\ChannelInterface;
use mirocow\queue\models\MessageModel;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Class BaseQueueChannel
 * @package mirocow\queue
 *
 * @property $driver \mirocow\queue\interfaces\DriverInterface
 */
class ChannelComponent extends Component implements ChannelInterface
{

    /**
     * @event PushEvent
     */
    const EVENT_BEFORE_PUSH = 'beforePush';
    /**
     * @event PushEvent
     */
    const EVENT_AFTER_PUSH = 'afterPush';

    /**
     * @var
     */
    public $driver;

    /**
     * @var string
     */
    public $queueName = 'queue';

    /**
     * @var string
     */
    public $channelName = "default";

    /**
     * @var int
     */
    public $pushDelay = 0;

    /**
     * @var null
     */
    public $pushPriority = NULL;

    /**
     * @throws InvalidConfigException
     */
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

    /**
     * @param null $queue
     * @return string
     */
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
    public function push(MessageModel $message, $delay = 0, $pushPriority = null) {
        $event = new PushEvent(
          [
            'message' => $message,
            'delay' => $delay,
            'priority' => $pushPriority,
          ]
        );
        $this->pushDelay    = 0;
        $this->pushPriority = NULL;
        $this->trigger(self::EVENT_BEFORE_PUSH, $event);

        $return = false;

        if ($message instanceof MessageModel) {
            if ($message->validate()) {
                $return = $this->driver->push($message->toJSON(), $this->getQueueName(), $delay, $pushPriority);
            } else {
                throw new ChannelException("message is not valid MessageModel");
            }
        } else {
            throw new ChannelException("message is not instanceof MessageModel");
        }

        $this->trigger(self::EVENT_AFTER_PUSH, $event);

        return $return;
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

    /**
     * @param $queueName
     * @return mixed
     */
    public function status($id = FALSE){
        return $this->driver->status($this->getQueueName(), $id);
    }
}