<?php

namespace mirocow\queue\controllers;

use Yii;
use yii\console\Controller;

/**
 * Class NotificationController
 * @package mirocow\queue\controllers
 */
class NotificationController extends Controller
{
    /**
     * @param string $triggerClass The class which is tied called method $methodName
     * @param string $methodName Class Method $triggerClass
     * @param array $arguments An array of arguments passed to the method $methodName
     */
    public function actionSend($triggerClass, $methodName, array $arguments = [])
    {
        if(empty($methodName)){
            return;
        }
    }
}
