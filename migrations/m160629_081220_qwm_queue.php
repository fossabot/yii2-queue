<?php

use yii\db\Migration;

class m160629_081220_qwm_queue extends Migration
{
    const TABLE_NAME = "{{%qwm_queue}}";
    const QUEUE_NAME_INDEX = 'idx-qwm_queue_name';
    const QUEUE_STATUS_INDEX = 'idx-qwm_queue_status';

    public function up()
    {
        $tableOptions = null;
        $columnMessageType = 'LONGBLOB NOT NULL';

        switch ($this->db->driverName) {
            case 'mysql':
                $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
                break;
            case 'pgsql':
                $columnMessageType = 'BYTEA';
                break;
        }

        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey(),
            'queue' => $this->string()->notNull(),
            'status' => $this->smallInteger()->notNull()->defaultValue(0),
            'message' => $columnMessageType
        ], $tableOptions);
        
        $this->createIndex(self::QUEUE_NAME_INDEX, self::TABLE_NAME, 'queue');
        $this->createIndex(self::QUEUE_STATUS_INDEX, self::TABLE_NAME, ['queue', 'status']);
    }

    public function down()
    {
        $this->dropTable(self::TABLE_NAME);
    }

}
