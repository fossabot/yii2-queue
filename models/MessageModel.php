<?php
/**
 * Created by PhpStorm.
 * User: Nikolay
 * Date: 29.06.2016
 * Time: 20:44
 */

namespace yii\queue\models;

use yii\base\Model;
use yii\helpers\Json;

/**
 * Class MessageModel
 * @package yii\queue\models
 *
 * @property string $worker
 * @property string $class this is optional property, no required
 * @property string $method
 * @property array $arguments
 */
class MessageModel extends Model
{
    public $worker;
    public $class;
    public $method;
    public $arguments = [];

    public function rules()
    {
        return array(
            [['worker', 'method'], 'required'],
            [['worker', 'class', 'method'], 'string'],
        );
    }

    /**
     * @param string $message
     * @return $this
     */
    public static function loadRawMessage($message = '')
    {
        if (!empty($message) && is_string($message)) {
            return new self(Json::decode($message));
        } else
            return null;
    }

    /**
     * Convert model to JSON
     *
     * @return string
     */
    public function toJSON()
    {
        return Json::encode($this->getAttributes());
    }

}