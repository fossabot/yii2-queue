<?php

namespace mirocow\queue\drivers\common;

/**
 * Class BaseConnection
 * @package mirocow\queue\drivers\common
 */
abstract class BaseConnection extends \yii\base\Component
{
    const STATUS_NEW = 0;
    const STATUS_DONE = 1;
    const STATUS_DELETE = -1;
    const STATUS_WAITING = 2;

    public $timeout = 100;
}