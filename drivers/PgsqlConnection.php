<?php

namespace mirocow\queue\drivers;

use mirocow\queue\drivers\common\BaseConnection;

/**
 * PgsqlConnection
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class PgsqlConnection extends BaseConnection
{
	public function delete(array $message)
	{
		
	}

	public function pop($queueName, $timeout = 0)
	{
		
	}

	public function purge(string $queueName)
	{
		
	}

	public function push($payload, string $queueName, $delay = 0, $priority = NULL)
	{
        if ($priority !== null) {
            throw new NotSupportedException('Job priority is not supported in the driver.');
        }
	}

	public function release(array $message, $delay = 0)
	{
		
	}

    public function status(string $queueName){

    }
}
