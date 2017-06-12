<?php

namespace mirocow\queue\exceptions;

/**
 * ChannelException
 *
 * @author Alexander Kochetov <creocoder@gmail.com>
 */
class ChannelException extends \yii\base\Exception
{

	/**
	 * @inheritdoc
	 */
	public function getName()
	{
		return 'Queue Exception';
	}

}
