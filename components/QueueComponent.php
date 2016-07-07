<?php
/**
 * Created by PhpStorm.
 * User: Nikolay
 * Date: 29.06.2016
 * Time: 15:08
 */

namespace yii\queue\components;

use yii\di\ServiceLocator;
use yii\queue\exceptions\QueueException;
use yii\queue\models\MessageModel;

/**
 * Main component of Yii queue
 *
 * Class QueueComponent
 * @package yii\queue\components
 * @property $regChannels ServiceLocator
 * @property $regWorkers ServiceLocator
 */
class QueueComponent extends \yii\base\Component implements \yii\queue\interfaces\QueueInterface
{
    public $queueName = 'queue';
    public $channels = [];
    public $workers = [];
    public $timeout = 1000;

    protected $regWorkers = null;
    protected $regChannels = null;

    private $_pid = null;

    public function init()
    {
        parent::init();

        if (!empty($this->channels)) {

            $this->regChannels = new ServiceLocator();

            foreach ($this->channels as $channelName => $channel) {
                $channel['queueName'] = $this->queueName;
                $channel['channelName'] = $channelName;
                $this->regChannels->set($channelName, $channel);
            }

        } else throw new QueueException("Empty channels!");

        if (!empty($this->workers)) {

            $this->regWorkers = new ServiceLocator();
            foreach ($this->workers as $workerName => $worker) {
                $channel['workerName'] = $workerName;
                $this->regWorkers->set($workerName, $worker);
            }

        } else throw new QueueException("Empty workers!");

    }

    /**
     * @param string $name
     * @return ChannelComponent
     * @throws QueueException
     */
    public function getChannel($name = '')
    {
        $name = empty($name) ? ($this->getChannelNamesList()[0] ?: '') : $name;

        if (isset($this->channels[$name])) {
            return $this->regChannels->{$name};
        } else {
            throw new QueueException("Channel `{$name}` not exist! Pls configure it before usage.");
        }
    }

    /**
     * @param $name
     * @return mixed
     * @throws QueueException
     */
    public function getWorker($name)
    {
        $name = empty($name) ? ($this->getChannelNamesList()[0] ?: '') : $name;

        if (isset($this->workers[$name])) {
            return $this->regWorkers->get($name);
        } else {
            throw new QueueException("Worker $name not exist!");
        }
    }

    public function getChannelNamesList()
    {
        return array_keys($this->channels);
    }

    public function getWorkerNamesList()
    {
        return array_keys($this->workers);
    }

    public function setPid($pid)
    {
        $this->_pid = $pid;
    }

    public function getPid()
    {
        return $this->_pid;
    }

    /**
     * @param MessageModel $messageModel
     * @return bool
     * @var $worker
     */
    public function processMessage(MessageModel $messageModel, $watcherId = null)
    {
        if ($worker = $this->getWorker($messageModel->worker)) {
            $worker->setMessage($messageModel);
            $worker->setWatcherId($watcherId);
            $worker->run();
        }
    }

    /**
     * @var $message MessageModel
     */
    public function startDaemon()
    {
        \Amp\run(function () {

            $this->setPid(getmypid());

            echo "Queue Daemon is started with PID: " . $this->getPid() . "\n\n";

            \Amp\onSignal(SIGINT, function () {
                \Amp\stop();
                throw new QueueException("Queue daemon terminate. PID: {$this->getPid()}\n\n");
            });

            foreach ($this->getChannelNamesList() as $channelName) {
                $channel = $this->getChannel($channelName);

                \Amp\repeat(function ($watcherId) use ($channel) {

                    if ($message = $channel->pop()) {
                        $this->processMessage($message, $watcherId);
                        return true;
                    } else {
                        return false;
                    }

                }, $this->timeout);
            }
        });
    }
}