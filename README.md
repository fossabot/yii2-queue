# yii2-queue
Yii 2.0 Queue Extension

Non blocking queue manager for Yii 2.0

##### Install:

`php composer.phar require mirocow/yii2-queue "dev-hakaton-tass"`

##### Config:

```php
'components' => [
    'queue' => [
        'class' => 'mirocow\queue\components\QueueComponent',
        'queueName' => 'default-queue',
        'timeout' => 50, // optional
        'workers' => [
            'notification' => [
                'class' => 'mirocow\queue\components\WorkerComponent',
                'action' => [
                    'class' => 'console\controllers\NotificationController',
                ]
            ],
            ...
        ],
        'channels' => [
            'default' => [
                'class' => 'mirocow\queue\components\ChannelComponent',
                    'driver' => [
                        'class' => 'mirocow\queue\drivers\MysqlConnection',
                        'connection' => 'db'
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

```php
\Yii::$app->queue->start(true);
```        


