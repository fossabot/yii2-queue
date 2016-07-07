<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\queue\interfaces;

/**
 * Worker inteface
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
interface WorkerInterface
{

    /**
     * Run worker
     */
    public function run();

    /**
     * Stop worker
     */
    public function stop();

    /**
     * Set Options
     *
     * @param array $options
     */
    public function setOptions(array $options);
}
