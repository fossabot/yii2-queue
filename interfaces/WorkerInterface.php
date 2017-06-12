<?php

namespace mirocow\queue\interfaces;

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
