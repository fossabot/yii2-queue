<?php

namespace mirocow\queue\drivers\common;

use mirocow\queue\interfaces\DriverInterface;

/**
 * Class BaseConnection
 * @package mirocow\queue\drivers\common
 */
abstract class BaseConnection extends \yii\base\Component implements DriverInterface
{
    const STATUS_NEW = 0;
    const STATUS_DONE = 1;
    const STATUS_DELETE = -1;
    const STATUS_WAITING = 2;

    public $timeout = 100;
}