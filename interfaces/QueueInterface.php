<?php

namespace mirocow\queue\interfaces;

/**
 * QueueInterface
 *
 * @author Alexander Kochetov <creocoder@gmail.com>
 */
interface QueueInterface
{

	/**
	 * Get channel object
	 *
	 * @param string $name
	 */
	public function getChannel($name);
	
	
}
