<?php

namespace mirocow\queue\components;

use mirocow\queue\models\MessageModel;
use yii\base\Event;

/**
 * Class JobEvent
 * @package mirocow\queue\components
 */
class JobEvent extends Event
{
    /**
     * @var string|null unique id of a job
     */
    public $id;

    /**
     * @var MessageModel
     */
    public $message;
}