<?php

namespace mirocow\queue\components;

/**
 * Class PushEvent
 * @package mirocow\queue\components
 */
class PushEvent extends JobEvent
{
    /**
     * @var int
     */
    public $delay;
    /**
     * @var mixed
     */
    public $priority;
}