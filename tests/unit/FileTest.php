<?php

use mirocow\queue\components\QueueComponent;
use mirocow\queue\models\MessageModel;
use Yii;

class FileTest extends \Codeception\Test\Unit
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

    public function testPush1()
    {
        $this->getQueue()->getChannel()->push(new MessageModel([
            'worker' => 'worker1',
            'method' => 'sayHello',
            'arguments' => [
                'say' => 'hello!'
            ]
        ]));
        $this->getQueue()->run();
        $this->assertFileExists(Yii::getAlias('@runtime/job-1.lock'));
    }

}
