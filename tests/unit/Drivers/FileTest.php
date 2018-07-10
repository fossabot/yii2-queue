<?php

use mirocow\queue\components\QueueComponent;
use mirocow\queue\models\MessageModel;
use Yii;

class FileTest extends \Codeception\Test\Unit
{

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
     * @throws \mirocow\queue\exceptions\ChannelException
     * @throws \mirocow\queue\exceptions\QueueException
     */
    public function testJobAsClassMethod()
    {
        /** @var QueueComponent $queue */
        $queue = Yii::$app->queue;
        $queue->getChannel('channel_with_driver_file')->push(new MessageModel([
            'worker' => 'worker',
            'method' => 'sayHello',
            'arguments' => [
                'say' => 'hello!'
            ]
        ]));
        $queue->run('channel_with_driver_file');
        $this->assertFileExists(Yii::getAlias('@runtime/job-1.lock'));
        unlink(Yii::getAlias('@runtime/job-1.lock'));
    }

}
