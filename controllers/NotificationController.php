<?php

namespace mirocow\queue\controllers;

use Yii;

/**
 * Class NotificationController
 * @package mirocow\queue\controllers
 */
class NotificationController
{
    /**
     * @param string $triggerClass The class which is tied called method $methodName
     * @param string $methodName Class Method $triggerClass
     * @param array $arguments An array of arguments passed to the method $methodName
     */
    static public function actionSend($triggerClass, $methodName, array $arguments = [])
    {
        if(empty($methodName)){
            return false;
        }
    }
}
