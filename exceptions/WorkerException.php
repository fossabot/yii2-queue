<?php

namespace mirocow\queue\exceptions;

/**
 * WorkerException
 *
 * @author Alexander Kochetov <creocoder@gmail.com>
 */
class WorkerException extends \yii\base\Exception
{

	/**
	 * @inheritdoc
	 */
	public function getName()
	{
		return 'Worker Exception';
	}

}
