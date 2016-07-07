<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\queue\drivers;

use yii\helpers\Json;

/**
 * MysqlConnection Driver
 *
 * @author Mitrofanov Nikolay <mitrofanovnk@gmail.com>
 */
class MysqlConnection extends \yii\base\Component implements \yii\queue\interfaces\DriverInterface
{

    const STATUS_NEW = 0;
    const STATUS_DONE = 1;
    const STATUS_DELETE = -1;

    public $connection;

    private $tableName = '{{%qwm_queue}}';

    public function getTableName()
    {
        return $this->tableName;
    }

    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
    }

    function init()
    {
        parent::init();

        $this->connection = \yii\di\Instance::ensure(
            \Yii::$app->{$this->connection},
            \yii\db\Connection::className()
        );
    }

    public function pop($queue)
    {
        $transaction = $this->connection->beginTransaction();

        $message = (new \yii\db\Query())
            ->select('*')
            ->from($this->getTableName())
            ->where(['status' => self::STATUS_NEW, 'queue' => $queue])
            ->limit(1)
            ->one($this->connection);
        if (!$message || $this->connection->createCommand()
                ->update(
                    $this->tableName,
                    ['status' => self::STATUS_DONE],
                    [
                        'id' => $message['id']
                    ]
                )->execute() !== 1
        ) {
            $transaction->rollBack();
            return false;
        };
        $transaction->commit();

        return $message['message'];
    }

    public function push($message, $queue, $delay = 0)
    {
        if (!is_string($message)) {
            $message = Json::encode($message);
        }

        return $this->connection->createCommand()->insert($this->getTableName(), [
            'message' => $message,
            'queue' => $queue
        ])->execute();
    }

    public function purge($queue)
    {
        return $this->connection->createCommand()->update($this->getTableName(), [
            'status' => self::STATUS_DELETE
        ], ['queue' => $queue]);
    }

    public function delete(array $message)
    {
        if (!empty($message)) {
            return $this->connection->createCommand()->update($this->getTableName(), [
                'status' => self::STATUS_DELETE
            ], ['id' => $message]);
        }
        return false;
    }

    public function release(array $message, $delay = 0)
    {

    }

}
