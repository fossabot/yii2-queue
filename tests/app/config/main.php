<?php
$config = [
    'id' => 'yii2-queue-app',
    'basePath' => dirname(__DIR__),
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    'runtimePath' => dirname(dirname(__DIR__)) . '/_data/runtime',
    'bootstrap' => [
    ],
    'components' => [
        'mysql' => [
            'class' => \yii\db\Connection::class,
            'dsn' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s',
                getenv('MYSQL_HOST') ?: 'localhost',
                getenv('MYSQL_PORT') ?: 3306,
                getenv('MYSQL_DATABASE') ?: 'yii2_queue_test'
            ),
            'username' => getenv('MYSQL_USER') ?: 'root',
            'password' => getenv('MYSQL_PASSWORD') ?: '',
            'charset' => 'utf8',
            'attributes' => [
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET sql_mode = "STRICT_ALL_TABLES"',
            ],
        ],
        'sqlite' => [
            'class' => \yii\db\Connection::class,
            'dsn' => 'sqlite:@runtime/yii2_queue_test.db',
        ],
        'pgsql' => [
            'class' => \yii\db\Connection::class,
            'dsn' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                getenv('POSTGRES_HOST') ?: 'localhost',
                getenv('POSTGRES_PORT') ?: 5432,
                getenv('POSTGRES_DB') ?: 'yii2_queue_test'
            ),
            'username' => getenv('POSTGRES_USER') ?: 'postgres',
            'password' => getenv('POSTGRES_PASSWORD') ?: '',
            'charset' => 'utf8',
        ],
        'redis' => [
            'class' => \yii\redis\Connection::class,
            'hostname' => getenv('REDIS_HOST') ?: 'localhost',
            'port' => getenv('REDIS_PORT') ?: 6379,
            'database' => getenv('REDIS_DB') ?: 10,
        ],
        'queue' => [
            'class' => 'mirocow\queue\components\QueueComponent',
            'queueName' => 'default-queue',
            'multithreading' => false,
            'workers' => [
                'worker' => [
                    'class' => 'mirocow\queue\components\WorkerComponent',
                    'action' => [
                        'class' => 'app\jobs\Job1',
                    ]
                ],
            ],
            'channels' => [
                'channel_with_driver_mysql' => [
                    'class' => 'mirocow\queue\components\ChannelComponent',
                    'driver' => [
                        'class' => 'mirocow\queue\drivers\MysqlConnection',
                        'connection' => 'mysql'
                    ]
                ],
                'channel_with_driver_redis' => [
                    'class' => 'mirocow\queue\components\ChannelComponent',
                    'driver' => [
                        'class' => 'mirocow\queue\drivers\RedisConnection',
                        'connection' => 'redis',
                        'timeout' => 50,
                    ]
                ],
                'channel_with_driver_file' => [
                    'class' => 'mirocow\queue\components\ChannelComponent',
                    'driver' => [
                        'class' => 'mirocow\queue\drivers\FileConnection',
                    ]
                ]
            ],
        ]
    ]
];

return $config;