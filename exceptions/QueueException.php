<?php

namespace mirocow\queue\exceptions;

/**
 * QueueException
 *
 * @author Alexander Kochetov <creocoder@gmail.com>
 */
class QueueException extends \yii\base\Exception
{

	/**
	 * @inheritdoc
	 */
	public function getName()
	{
		return 'Worker Exception';
	}

}
