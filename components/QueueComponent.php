<?php

namespace mirocow\queue\components;

use mirocow\queue\exceptions\QueueException;
use mirocow\queue\models\MessageModel;
use yii\di\ServiceLocator;

/**
 * Main component of Yii queue
 *
 * Class QueueComponent
 * @package mirocow\queue\components
 * @property $regChannels ServiceLocator
 * @property $regWorkers ServiceLocator
 */
class QueueComponent extends \yii\base\Component implements \mirocow\queue\interfaces\QueueInterface
{
    public $queueName = 'queue';
    public $channels = [];
    public $workers = [];
    public $timer_tick = 1000;
    public $timer_keep_alive = true;
    public $pidFile = '';

    protected $regWorkers = null;
    protected $regChannels = null;

    private $_pid = null;

    /**
     * @throws QueueException
     */
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
    public function getChannel($name = 'default')
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

    /**
     * @return array
     */
    public function getChannelNamesList()
    {
        return array_keys($this->channels);
    }

    /**
     * @return array
     */
    public function getWorkerNamesList()
    {
        return array_keys($this->workers);
    }

    /**
     * @param $pid
     */
    public function setPid($pid)
    {
        $this->_pid = $pid;
    }

    /**
     * @return null
     */
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
            try {
                $worker->run();
            } catch (\Exception $e) {
                \Yii::error($e, __METHOD__);
                throw $e;
            }
        }
    }

    /**
     * @var $message MessageModel
     */
    public function start($daemon = true)
    {

        \Amp\run(function () {

            $this->setPid(getmypid());

            if($this->pidFile){
                file_put_contents($this->pidFile, $this->getPid());
            }

            echo "Queue Daemon is started with PID: " . $this->getPid() . "\n\n";

            if(true !== $this->addSignals()) {
                throw new \Exception('No signals!');
            }

            foreach ($this->getChannelNamesList() as $channelName) {
                $channel = $this->getChannel($channelName);

                \Amp\repeat(function ($watcherId) use ($channel) {

                    try {
                        if ($message = $channel->pop()) {
                            $this->processMessage($message, $watcherId);
                            return TRUE;
                        }
                        else {
                            return FALSE;
                        }
                    } catch (\Exception $e){
                        if($e->getCode() == 0) {
                            \Yii::error($e, __METHOD__);
                            return FALSE;
                        }
                    }

                }, $this->timer_tick,  $options = ['keep_alive' => $this->timer_keep_alive]);
            }
        });
    }

    /**
     * @return bool
     */
    private function addSignals()
    {
        if (php_sapi_name() === "phpdbg") {
            // phpdbg captures SIGINT so don't bother inside the debugger
            return;
        }

        \Amp\onSignal(SIGINT, function () {
            \Amp\stop();
        });
        \Amp\onSignal(SIGTERM, function () {
            \Amp\stop();
        });
        \Amp\onSignal(SIGHUP, function () {
            \Amp\stop();
        });

        register_shutdown_function(function () {
            if (\Amp\info()["state"] !== \Amp\Reactor::STOPPED) {
                \Amp\stop();
                throw new QueueException("Queue daemon terminate. PID: {$this->getPid()}\n\n");
            }
        });

        return true;
    }
}