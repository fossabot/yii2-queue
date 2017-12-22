<?php

namespace mirocow\queue\controllers;

use common\models\essence\Ads;
use common\models\User;
use common\models\essence\Chats;
use common\models\essence\Message;
use mirocow\notification\components\Notification;
use yii\console\Controller;
use yii\db\Expression;
use yii\helpers\Url;

/**
 * Service for dispatch emails
 *
 * php ./yii queue/run
 * Class QueueController
 * @package console\controllers
 */
class QueueController extends Controller
{
    public $pid_file = '';

    public function __get($name)
    {
        $name = str_replace('-', '_', $name);

        return $this->$name;
    }

    public function __set($name, $value)
    {
        $name = str_replace('-', '_', $name);

        $this->$name = $value;
    }

    public function options($actionID)
    {
        return ['pid-file'];
    }

    /**
     * Run service
     */
    public function actionRun()
    {
        $queue = \Yii::$app->queue;
        $queue->pidFile = $this->pid_file;
        $queue->start();
    }
}