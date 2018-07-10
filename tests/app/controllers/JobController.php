<?php

namespace app\controllers;

use yii\console\Controller;
use Yii;

/**
 * JobController.
 */
class JobController extends Controller
{
    public function actionSayHello($say)
    {
        $fileName = Yii::getAlias('@runtime/job-1.lock');
        file_put_contents($fileName, $say);
    }

    public static function sayHelloStatic($say){
        $fileName= Yii::getAlias('@runtime/job-2.lock');
        file_put_contents($fileName, $say);
    }

}