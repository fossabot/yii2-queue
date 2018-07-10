<?php
/**
 * Created by PhpStorm.
 * User: mirocow
 * Date: 08.07.2018
 * Time: 3:44
 */

namespace app\jobs;

use Yii;

class Job1
{
    public function sayHello($say){
        $fileName = Yii::getAlias('@runtime/job-1.lock');
        file_put_contents($fileName, $say);
    }

    public static function sayHelloStatic($say){
        $fileName = Yii::getAlias('@runtime/job-2.lock');
        file_put_contents($fileName, $say);
    }
}