<?php

use mirocow\queue\components\QueueComponent;
use mirocow\queue\models\MessageModel;
use Yii;

/**
 * Class ActionsTest
 */
class ActionsTest extends \Codeception\Test\Unit
{
    private $_queue;

    /**
     * @var \UnitTester
     */
    protected $tester;
    
    protected function _before()
    {
    }

    protected function _after()
    {
        foreach (glob(Yii::getAlias("@runtime/job-*.lock")) as $fileName) {
            unlink($fileName);
        }
    }

    /**
     * @return QueueComponent
     */
    protected function getQueue()
    {
        if (!$this->_queue) {
            $this->_queue = new QueueComponent([
                'queueName' => 'default-queue',
                'multithreading' => false,
                'workers' => [
                    'worker1' => [
                        'class' => 'mirocow\queue\components\WorkerComponent',
                        'action' => [
                            'class' => 'app\jobs\Job1',
                        ]
                    ],
                    'worker2' => [
                        'class' => 'mirocow\queue\components\WorkerComponent',
                        'action' => [
                            'class' => 'app\controllers\JobController',
                        ]
                    ],
                ],
                'channels' => [
                    'default' => [
                        'class' => 'mirocow\queue\components\ChannelComponent',
                        'driver' => [
                            'class' => 'mirocow\queue\drivers\FileConnection',
                        ]
                    ],
                ],
            ]);
        }
        return $this->_queue;
    }

    public function testRunJobAsClassMethod()
    {
        $this->getQueue()->getChannel()->push(new MessageModel([
            'worker' => 'worker1',
            'method' => 'sayHello',
            'arguments' => [
                'say' => 'hello!'
            ]
        ]));
        $this->getQueue()->run('default');
        $this->assertFileExists(Yii::getAlias('@runtime/job-1.lock'));
        unlink(Yii::getAlias('@runtime/job-1.lock'));
        //unset($this->_queue);
    }

    public function testRunJobClassStaticMethod()
    {
        $this->getQueue()->getChannel()->push(new MessageModel([
            'worker' => 'worker1',
            'method' => 'sayHelloStatic',
            'arguments' => [
                'say' => 'hello!'
            ]
        ]));
        $this->getQueue()->run('default');
        $this->assertFileExists(Yii::getAlias('@runtime/job-1.lock'));
        unlink(Yii::getAlias('@runtime/job-1.lock'));
        //unset($this->_queue);
    }

    /**
     * @throws \mirocow\queue\exceptions\ChannelException
     * @throws \mirocow\queue\exceptions\QueueException
     */
    public function testRunJobAsActionOfController()
    {
        $this->getQueue()->getChannel()->push(new MessageModel([
            'worker' => 'worker2',
            'method' => 'actionSayHello',
            'arguments' => [
                'say' => 'hello!'
            ]
        ]));
        $this->getQueue()->run('default');
        $this->assertFileExists(Yii::getAlias('@runtime/job-1.lock'));
        unlink(Yii::getAlias('@runtime/job-1.lock'));
        //unset($this->_queue);
    }

    /**
     * @throws \mirocow\queue\exceptions\ChannelException
     * @throws \mirocow\queue\exceptions\QueueException
     */
    public function testRunJobAsStaticMethod()
    {
        $this->getQueue()->getChannel()->push(new MessageModel([
            'worker' => 'worker2',
            'method' => 'sayHelloStatic',
            'arguments' => [
                'say' => 'hello!'
            ]
        ]));
        $this->getQueue()->run('default');
        $this->assertFileExists(Yii::getAlias('@runtime/job-1.lock'));
        unlink(Yii::getAlias('@runtime/job-1.lock'));
        //unset($this->_queue);
    }

}
