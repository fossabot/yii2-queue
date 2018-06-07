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

    /**
     * Version.
     *
     * @var string
     */
    const VERSION = '0.0.3';

    const OS_TYPE_LINUX = 0;

    const OS_TYPE_WIN = 1;

    /**
     * Daemonize.
     *
     * @var bool
     */
    public static $_daemonize = false;

    /**
     * OS.
     *
     * @var string
     */
    protected static $_OS = self::OS_TYPE_LINUX;

    /**
     * Standard output stream
     * @var resource
     */
    protected static $_outputStream = null;

    /**
     * If $outputStream support decorated
     * @var bool
     */
    protected static $_outputDecorated = null;

    public $queueName = 'queue';
    public $channels = [];
    public $workers = [];
    public $timer_tick = 10;
    public $pidFile = '';
    public $delayForIfRiseException = 300;
    public $daemonize = true;

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

        self::$_daemonize = $this->daemonize;

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
     * @var $watcherId
     * @var integer $pid
     */
    private function processMessage(MessageModel $messageModel, $watcherId = null, $pid)
    {
        /** @var WorkerComponent $worker */
        if ($worker = $this->getWorker($messageModel->worker)) {
            pcntl_signal_dispatch();
            try {
                $this->log("Child process {$pid} are working...\n");
                $worker->setMessage($messageModel);
                $worker->setWatcherId($watcherId);
                $worker->run();
                $this->log("Child process {$pid} finished\n");
            } catch (\Exception $e) {
                $this->log("Rise exception \"".$e->getMessage()."\" in child {$pid} process\n");
                $this->log("File " . $e->getFile() . " (" . $e->getLine(). ")\n");
                if($worker->repeatIfRiseException) {
                    $channel->push($message, $this->delayForIfRiseException);
                }
                throw $e;
            } catch (\Throwable $e) {
                $this->log("Rise exception \"".$e->getMessage()."\" in child {$pid} process\n");
                $this->log("File " . $e->getFile() . " (" . $e->getLine(). ")\n");
                if($worker->repeatIfRiseException) {
                    $channel->push($message, $this->delayForIfRiseException);
                }
                throw $e;
            }
        }
    }

    /**
     * Start daemon
     */
    public function start()
    {
        Loop::run(function () {

            if (true !== $this->addSignals()) {
                throw new \Exception('No signals!');
            }

            self::daemonize();

            $this->setPid(getmypid());

            if ($this->pidFile) {
                file_put_contents($this->pidFile, $this->getPid());
            }

            self::displayUI($this);

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
                        $this->log("Rise exception \"".$e->getMessage()."\n");
                        if ($this->isChild) {
                            exit(0);
                        } else {
                            Loop::stop();
                        }
                    } catch (\Throwable $e) {
                        \Yii::error($e, __METHOD__);
                        $this->log("Rise exception \"".$e->getMessage()."\n");
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
            $this->setSignalHandlers([$this, 'childSignalHandler']);
            $this->processMessage($message, $watcherId, $pid);
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
                self::log("Child {$signaled_pid} done\n");
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
                $this->log("Signal {$signo} does not have handlers\n");
        }
    }

    /**
     * Child singanal handler
     * @param $signo
     */
    public function childSignalHandler($signo)
    {
        switch ($signo) {
            case SIGTERM:
            case SIGINT:
            case SIGHUP;
                $this->log("Child process catch signal {$signo}\n");
                break;
            default:
                $this->log("Signal {$signo} does not have handlers\n");
        }
    }

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
        static::safeEcho($message, true);
    }

    /**
     * Run as deamon mode.
     *
     * @throws Exception
     */
    protected static function daemonize()
    {
        if (!static::$_daemonize || static::$_OS !== self::OS_TYPE_LINUX) {
            return;
        }
        umask(0);
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new Exception('fork fail');
        } elseif ($pid > 0) {
            exit(0);
        }
        if (-1 === posix_setsid()) {
            throw new Exception("setsid fail");
        }
        // Fork again avoid SVR4 system regain the control of terminal.
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new Exception("fork fail");
        } elseif (0 !== $pid) {
            exit(0);
        }
    }

    /**
     * Display staring UI.
     *
     * @param $instance
     * @return void
     */
    protected static function displayUI($instance)
    {
        global $argv;
        if (in_array('-q', $argv)) {
            return;
        }

        $pid = getmypid();

        static::safeEcho("----------------------- YII2-QUEUE -----------------------------\r\n");
        static::safeEcho('Yii2-queue version: ' . static::VERSION . "          PHP version:" . PHP_VERSION . "\r\n");
        static::safeEcho('Process ID: '. $pid . "\r\n");
        static::safeEcho('Channels: '. count($instance->channels) . "\r\n");
        static::safeEcho('Workers: '. count($instance->workers) . "\r\n");
        static::safeEcho('Default queue name: ' . $instance->queueName . "\r\n");
        static::safeEcho("----------------------------------------------------------------\n");

        if (static::$_OS !== self::OS_TYPE_LINUX) {
            return;
        }

        if (static::$_daemonize) {
            static::safeEcho("Input \"kill -2 {$pid}\" to stop. Start success.\n\n");
        } else {
            static::safeEcho("Press Ctrl+C to stop. Start success.\n");
        }
        
    }

    /**
     * Safe Echo.
     * @param $msg
     * @param bool $decorated
     * @return bool
     */
    protected static function safeEcho($msg, $decorated = false)
    {
        $stream = static::outputStream();
        if (!$stream) {
            return false;
        }
        if (!$decorated) {
            $line = $white = $green = $end = '';
            if (static::$_outputDecorated) {
                $line = "\033[1A\n\033[K";
                $white = "\033[47;30m";
                $green = "\033[32;40m";
                $end = "\033[0m";
            }
            $msg = str_replace(['<n>', '<w>', '<g>'], [$line, $white, $green], $msg);
            $msg = str_replace(['</n>', '</w>', '</g>'], $end, $msg);
        } elseif (!static::$_outputDecorated) {
            return false;
        }
        fwrite($stream, $msg);
        fflush($stream);
        return true;
    }

    /**
     * @param null $stream
     * @return bool|resource
     */
    protected static function outputStream($stream = null)
    {
        if (!$stream) {
            $stream = static::$_outputStream ? static::$_outputStream : STDOUT;
        }
        if (!$stream || !is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            return false;
        }
        $stat = fstat($stream);
        if (($stat['mode'] & 0170000) === 0100000) {
            // file
            static::$_outputDecorated = false;
        } else {
            static::$_outputDecorated =
                static::$_OS === self::OS_TYPE_LINUX &&
                function_exists('posix_isatty') &&
                posix_isatty($stream);
        }
        return static::$_outputStream = $stream;
    }

}
