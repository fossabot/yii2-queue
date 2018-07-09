<?php
define('YII_DEBUG', true);
$_SERVER['SCRIPT_NAME'] = '/' . __DIR__;
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
require_once(dirname(__DIR__) . '../../../../vendor/autoload.php');
require_once(dirname(__DIR__) . '../../../../vendor/yiisoft/yii2/Yii.php');
Yii::setAlias('@yii/queue', dirname(__DIR__));
Yii::setAlias('@yii/queue/mysql', dirname(__DIR__) . '/drivers/MysqlConnection');
Yii::setAlias('@yii/queue/file', dirname(__DIR__) . '/drivers/FileConnection');
Yii::setAlias('@yii/queue/redis', dirname(__DIR__) . '/drivers/RedisConnection');
Yii::setAlias('@yii/queue/sqlite', dirname(__DIR__) . '/drivers/SqLiteConnection');
Yii::setAlias('@tests', __DIR__);
$config = require(__DIR__ . '/app/config/main.php');
$app = new \yii\console\Application($config);