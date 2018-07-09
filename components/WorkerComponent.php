<?php

namespace mirocow\queue\components;

use yii\base\Component;
use yii\base\Controller;
use yii\base\InvalidConfigException;
use mirocow\queue\exceptions\ChannelException;
use mirocow\queue\exceptions\WorkerException;
use mirocow\queue\interfaces\WorkerInterface;
use mirocow\queue\models\MessageModel;

/**
 * Class WorkerComponent
 * @package mirocow\queue
 *
 * @property $actionClass \yii\console\Controller
 * @property $_message MessageModel
 */
class WorkerComponent extends Component implements WorkerInterface
{

    public $id;
    public $action;
    public $workerName;

    public static $actionClassName = '';
    public static $isRun = false;

    /**
     * @var MessageModel null
     */
    private $_message = null;
    private $_watcherId = null;
    private $_validClassMethods = [];
    private $_actionClass = null;

    /**
     *
     */
    public function init()
    {
        if(!$this->id){
            $this->id = 'id_' . time();
        }
        parent::init();
        $this->setActionClass($this->constructActionClass($this->action));
    }

    /**
     * @param $action
     * @return null
     */
    private function constructActionClass($action)
    {
        $actionClass = null;
        try {
            if (is_array($action) && isset($action['class'])) {
                $actionClass = new $action['class']($this->workerName, $this);
            } elseif (class_exists($action)) {
                $actionClass = new $action();
            }
        } catch (WorkerException $wEx) {
            throw new $wEx('Can\'t create action class!');
        }
        return $actionClass;
    }

    /**
     * @param null $class
     * @return bool
     */
    public function setActionClass($class = null)
    {
        if (is_object($class)) {
            $this->_actionClass = $class;
            $this::$actionClassName = get_class($class);
            return true;
        }
        return false;
    }

    /**
     * @return null
     */
    public function getActionClass()
    {
        return $this->_actionClass;
    }

    /**
     * @param null $watcherId
     */
    public function setWatcherId($watcherId = null)
    {
        $this->_watcherId = $watcherId;
    }

    /**
     * @return null
     */
    public function getWatcherId()
    {
        return $this->_watcherId;
    }

    /**
     * @param MessageModel $messageModel
     * @return bool
     * @throws WorkerException
     */
    public function setMessage(MessageModel $messageModel)
    {
        $this->_message = $messageModel;
        if (empty($this::$actionClassName) && !empty($messageModel->actionClassName)) {
            if (!$this->setActionClass($this->constructActionClass($messageModel->actionClassName))) {
                throw new WorkerException("Not exist action class!");
            }
        }
        return true;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->_message;
    }

    /**
     * @param $class
     * @param $method
     * @return bool
     */
    private function methodInClassValidate($class, $method)
    {
        $classHasMethod = false;

        if (isset($this->_validClassMethods[$class]) && isset($this->_validClassMethods[$class][$method])) {
            $classHasMethod = true;
        } elseif (method_exists($class, $method)
            && is_callable([$class, $method])
        ) {
            $this->_validClassMethods[$class] = [];
            $this->_validClassMethods[$class][$method] = new \ReflectionMethod($class, $method);;

            $classHasMethod = true;
        }

        return $classHasMethod;
    }

    private function isStatic($class, $method)
    {
        return $this->_validClassMethods[$class][$method]->isStatic();
    }

    /**
     * @param $class
     * @param $method
     * @param $arguments
     * @return bool
     * @throws WorkerException
     * @var \ReflectionFunction $refFunc
     */
    private function argumentsValidate($class, $method, $arguments)
    {
        if (isset($this->_validClassMethods[$class]) && isset($this->_validClassMethods[$class][$method])) {
            $refFunc = $this->_validClassMethods[$class][$method];
            $userArguments = array_keys($arguments);
            $missingArguments = [];
            foreach ($refFunc->getParameters() as $param) {
                if (!$param->isOptional() && !in_array($param->getName(), $userArguments)) {
                    $missingArguments[] = $param->getName();
                } else {
                    if ($param->getClass()
                        && (
                            !is_object($arguments[$param->getName()])
                            || get_class($arguments[$param->getName()]) !== $param->getClass()->name
                        )
                    ) {
                        throw new WorkerException(
                            "Method `{$method}` param `{$param->getName()}` " .
                            "expects type `{$param->getClass()->name}` but got " . gettype($arguments[$param->getName()])
                        );
                    }
                }
            }
            if (sizeof($missingArguments)) {
                throw new WorkerException(
                    "Method `{$method}` missing required arguments: " . implode(
                        ', ',
                        $missingArguments
                    )
                );
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return mixed
     * @throws WorkerException
     */
    public function run()
    {
        /** @var MessageModel $message */
        $message = $this->getMessage();
        $actionClassName = $this::$actionClassName;

        if ($this->methodInClassValidate($actionClassName, $message->method)) {
            if($this->argumentsValidate($actionClassName, $message->method, $message->arguments)){
                if($this->isStatic($actionClassName, $message->method)) {
                    if(!isset($message->arguments['worker'])){
                        $message->arguments['worker'] = $this;
                    }
                    return call_user_func_array([ $actionClassName, $message->method ], $message->arguments);
                } else {
                    $reflection = new \ReflectionClass($actionClassName);
                    $constructor = $reflection->getConstructor();
                    if($constructor){
                        // TODO 5: Check for a need to use the property "worker"
                        $object = \Yii::createObject($this->action, [$this->id, $this]);
                    } else {
                        $object = \Yii::createObject($this->action);
                        if($reflection->hasProperty('worker')){
                            $object->worker = $this;
                        }
                    }
                    return call_user_func_array([ $object, $message->method ], $message->arguments);
                }
            }
            throw new WorkerException("Send wrong variables to method `{$message->method}` in class `{$actionClassName}`");
        } else {
            throw new WorkerException("Method `{$message->method}` not exist in class `{$actionClassName}`");
        }
    }

    /**
     * Stop worker
     */
    public function stop()
    {
        if ($watcherId = $this->getWatcherId()) {
            \Amp\cancel($watcherId);
        }
    }

    /**
     * Set Options
     *
     * @param array $options
     */
    public function setOptions(array $options)
    {
        if (!empty($options)) {
            foreach ($options as $optionName => $optionValue) {
                $this->{$optionName} = $optionValue;
            }
        }
    }
}