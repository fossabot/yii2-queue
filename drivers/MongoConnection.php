<?php

namespace mirocow\queue\drivers;

use mirocow\queue\drivers\common\BaseConnection;

/**
 * MongoConnection
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class MongoConnection extends BaseConnection
{
	public function delete(array $message)
	{
		
	}

	public function pop(string $queueName, $timeout = 0)
	{
		
	}

	public function purge(string $queueName)
	{
		
	}

    /**
     * @param string $payload
     * @param string $queueName
     * @param int $delay
     * @param null $priority
     */
	public function push($payload, string $queueName, $delay = 0, $priority = NULL)
	{
        if ($priority !== null) {
            throw new NotSupportedException('Job priority is not supported in the driver.');
        }
	}

	public function release(array $message, $delay = 0)
	{
		
	}

    /**
     * @param string $queueName
     */
    public function status(string $queueName){

    }

}
