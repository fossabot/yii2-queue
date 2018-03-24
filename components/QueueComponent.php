<?php

namespace mirocow\queue\components;

use mirocow\queue\exceptions\QueueException;
use mirocow\queue\models\MessageModel;
use yii\di\ServiceLocator;
use Amp\Loop;

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
    public $timer_tick = 10;
    public $pidFile = '';
    public $delayForIfRiseException = 300;

    protected $regWorkers = null;
    protected $regChannels = null;

    private $_pid = null;
    private $_children = [];
    private $isChild = false;
    private $_active = true;

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
                $worker['workerName'] = $workerName;
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
     * @var $worker
     */
    private function processMessage(MessageModel $messageModel, $watcherId = null)
    {
        if ($worker = $this->getWorker($messageModel->worker)) {
            $worker->setMessage($messageModel);
            $worker->setWatcherId($watcherId);
            $worker->run();
        }
    }

    /**
     * Start daemon
     */
    public function start()
    {
        Loop::run(function () {

            $this->setPid(getmypid());

            if ($this->pidFile) {
                file_put_contents($this->pidFile, $this->getPid());
            }

            if (true !== $this->addSignals()) {
                throw new \Exception('No signals!');
            }

            $this->log("Queue Daemon is started with PID: " . $this->getPid() . "\n\n");
            foreach ($this->getChannelNamesList() as $channelName) {
                /** @var ChannelComponent $channel */
                $channel = $this->getChannel($channelName);
                Loop::repeat($this->timer_tick, function ($watcherId, $channel) {
                    pcntl_signal_dispatch();
                    try {
                        if ($message = $channel->pop()) {
                            $this->child($message, $watcherId, $channel);
                            $this->setSignalHandlers([$this, 'mainSignalHandler']);
                            $this->waitChildren();
                        }
                    } catch (\Exception $e) {
                        \Yii::error($e, __METHOD__);
                        if ($this->isChild) {
                            exit(0);
                        } else {
                            Loop::stop();
                        }
                    } catch (\Throwable $e) {
                        \Yii::error($e, __METHOD__);
                        if ($this->isChild) {
                            exit(0);
                        } else {
                            Loop::stop();
                        }
                    }
                    // Notify the children of the completion of work
                    $this->sendSignal(SIGINT);
                    // Set all signals
                    pcntl_signal_dispatch();
                }, $channel);
            }
        });
    }

    /**
     * @param array $message
     * @param $watcherId
     * @param ChannelComponent $channel
     * @throws \Exception
     * @throws \Throwable
     * @throws \mirocow\queue\exceptions\ChannelException
     */
    protected function child($message = [], $watcherId, ChannelComponent $channel)
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new \Exception("Can`t fork process");
        } else if ($pid) {
            $this->_children[] = $pid;
        } else {
            $pid = posix_getpid();
            $this->log("Child process starting with PID {$pid} ...\n");
            $this->isChild = true;
            //$this->setSignalHandlers([$this, 'childSignalHandler']);
            pcntl_signal_dispatch();
            try {
                $this->log("Child process {$pid} are working...\n");
                $this->processMessage($message, $watcherId);
                $this->log("Child process finished\n");
            } catch (\Exception $e) {
                $this->log("Rise exception \"".$e->getMessage()."\" in child {$pid} process\n");
                $this->log("File " . $e->getFile() . " (" . $e->getLine(). ")\n");
                $channel->push($message, $this->delayForIfRiseException);
                throw $e;
            } catch (\Throwable $e) {
                $this->log("Rise exception \"".$e->getMessage()."\" in child {$pid} process\n");
                $this->log("File " . $e->getFile() . " (" . $e->getLine(). ")\n");
                $channel->push($message, $this->delayForIfRiseException);
                throw $e;
            }
            exit(0);
        }
    }

    /**
     * Wait till child process send gignal
     */
    protected function waitChildren()
    {
        while (($signaled_pid = pcntl_waitpid(-1, $status, WNOHANG)) || count($this->_children) > 0) {
            pcntl_signal_dispatch();
            if ($signaled_pid == -1) {
                $this->_children = [];
                break;
            } elseif ($signaled_pid) {
                echo "Child {$signaled_pid} done\n";
                unset($this->_children[$signaled_pid]);
            }
        }
    }

    /**
     * Main singnal handler
     * @param $signo
     */
    public function mainSignalHandler($signo)
    {
        switch ($signo) {
            case SIGTERM:
            case SIGINT:
            case SIGHUP;
                $this->log("Main process catch signal {$signo}\n");
                $this->sendSignal(SIGINT);
                Loop::stop();
                break;
            default:
                $this->log("Signal {$signo} does not have handlers");
        }
    }

    /**
     * Child singanal handler
     * @param $signo
     */
    /*public function childSignalHandler($signo)
    {
        switch ($signo) {
            case SIGTERM:
            case SIGINT:
            case SIGHUP;
                $this->log("Child process catch signal {$signo}\n");
                break;
            default:
                $this->log("Signal {$signo} does not have handlers");
        }
    }*/

    /**
     * Set pcntl signal handler
     * @param $handler
     * @return bool
     * @throws \Exception
     */
    protected function setSignalHandlers($handler)
    {
        if (!function_exists('pcntl_signal_dispatch')) {
            throw new \Exception('Not installed function pcntl_signal_dispatch');
        }
        if (!function_exists('pcntl_signal')) {
            throw new \Exception('Not installed function pcntl_signal');
        }
        if (
            pcntl_signal(SIGTERM, $handler) &&
            pcntl_signal(SIGINT, $handler) &&
            pcntl_signal(SIGHUP, $handler)
        ) {
            return true;
        } else {
            throw new \Exception('Not set handler pcntl_signal');
        }
    }

    /**
     * Set amphp signal hendler
     * @return bool
     */
    private function addSignals()
    {
        if (php_sapi_name() === "phpdbg") {
            // phpdbg captures SIGINT so don't bother inside the debugger
            return;
        }

        Loop::onSignal(SIGINT, function () {
            $this->log("Server has killed\n");
            Loop::stop();
        });
        Loop::onSignal(SIGTERM, function () {
            $this->log("Server has killed\n");
            Loop::stop();
        });
        Loop::onSignal(SIGHUP, function () {
            $this->log("Server has killed\n");
            Loop::stop();
        });

        return true;
    }

    /**
     * Send signal to children processes
     * @param int $signo\
     */
    private function sendSignal($signo = SIGINT)
    {
        foreach ($this->_children as $_childrenPid) {
            $this->log("Send signal {$signo} to child process {$_childrenPid}\n");
            if(posix_kill($_childrenPid, $signo)){
                $this->log("Child process $_childrenPid signal {$signo} catched\n");
            }
        }
    }

    /**
     * Output debug message (work only YII_DEBUG = true)
     * @param $message
     */
    private function log($message)
    {
        if(YII_DEBUG){
            echo $message;
        }
    }

}
