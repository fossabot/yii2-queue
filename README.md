# yii2-queue
Yii 2.0 Queue Extension

Non blocking queue manager for Yii 2.0. It supports queues based on DB, Redis

```
----------------------- YII2-QUEUE -----------------------------
Yii2-queue version: 0.0.3          PHP version:7.0.27-0+deb9u1
Process ID: 6930
Channels: 1
Workers: 4
Default queue name: default-queue
----------------------------------------------------------------
Press Ctrl+C to stop. Start success.
Child process starting with PID 6880 ...
Child process 6880 are working...
Child 6880 done
Child process starting with PID 6882 ...
Child process 6882 are working...
Run worker [web] with action [ProductCreate] from common\models\essence\Product
...
```

##### Install:

`php composer.phar require mirocow/yii2-queue "dev-hakaton-tass"`

##### Config:

```php
'controllerMap' => [
    'queue' => [
        'class' => 'mirocow\queue\controllers\QueueController',
    ],
],

'components' => [

    'queue' => [
        'class' => 'mirocow\queue\components\QueueComponent',
        'queueName' => 'default-queue',
        'workers' => [
            'notification' => [
                'class' => 'mirocow\queue\components\WorkerComponent',
                'action' => [
                    'class' => 'mirocow\queue\controllers\NotificationController',
                ]
            ],
            ...
        ],
        'channels' => [
            'default' => [
                'class' => 'mirocow\queue\components\ChannelComponent',
                    'driver' => [
                        'class' => 'mirocow\queue\drivers\MysqlConnection',
                        'connection' => 'db',
                    ]
                ]
            ],
            ...
        ]
    ]
]
```

Before use apply migrations for using Mysql driver:
```php
./yii migrate/up --migrationPath=@vendor/argayash/yii2-queue/migrations
```

### Usage:

#### Worker class:

```php
namespace \console\controllers;

class NotificationController extends Controller
{
    public function actionSayHello($say)
    {
        \Yii::info($say);
    }
}
```

#### Push message to queue:

```php
Yii::$app->queue->getChannel('default')->push(
    new MessageModel([
        'worker' => 'notification',
        'method' => 'actionSayHello',
        'arguments' => [
            'say' => 'hello!'
        ]
    ])
);
```

#### Run queue worker daemon (console app):

```bash
$ php ./yii queue/run --pid-file=/tmp/queue.pid
```        

##### Test:

```bash
$ ./vendor/bin/codecept -c vendor/mirocow/yii2-queue run unit
```

