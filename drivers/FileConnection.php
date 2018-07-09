<?php

namespace mirocow\queue\drivers;

use mirocow\queue\drivers\common\BaseConnection;
use mirocow\queue\interfaces\DriverInterface;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use Yii;
use yii\helpers\FileHelper;

/**
 * FileConnection
 *
 * @author Roman Zhuravlev <zhuravljov@gmail.com>
 */
class FileConnection extends BaseConnection implements DriverInterface
{

    /**
     * @var string
     */
    public $path = '@runtime/queue';

    /**
     * @var int
     */
    public $dirMode = 0755;

    /**
     * @var int|null
     */
    public $fileMode = 0664;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->path = Yii::getAlias($this->path);
        if (!is_dir($this->path)) {
            FileHelper::createDirectory($this->path, $this->dirMode, true);
        }
    }

    /**
     * Push payload to the storage.
     * @param $payload
     * @param $queueName
     * @param int $delay
     * @param null $priority
     * @return mixed
     */
    public function push(string $payload, string $queueName, $delay = 0, $priority = NULL)
    {
        if ($priority !== null) {
            throw new NotSupportedException('Job priority is not supported in the driver.');
        }

        $ttr = 0;

        $this->touchIndex($queueName, function (&$data) use ($payload, $ttr, $delay, &$id) {
            if (!isset($data['lastId'])) {
                $data['lastId'] = 0;
            }
            $id = ++$data['lastId'];
            $fileName = "{$this->path}/job__{$queueName}__{$id}.data";
            file_put_contents($fileName, $payload);

            if ($this->fileMode !== null) {
                chmod($fileName, $this->fileMode);
            }

            if (!$delay) {
                $data['waiting'][] = [$id, $ttr, 0];
            } else {
                $data['delayed'][] = [$id, $ttr, time() + $delay];
                usort($data['delayed'], function ($a, $b) {
                    if ($a[2] < $b[2]) {
                        return -1;
                    }
                    if ($a[2] > $b[2]) {
                        return 1;
                    }
                    if ($a[0] < $b[0]) {
                        return -1;
                    }
                    if ($a[0] > $b[0]) {
                        return 1;
                    }
                    return 0;
                });
            }
        });

        return $id;
    }

    /**
     * Pops message from the storage.
     *
     * @param string $queueName
     * @return array|false
     */
    public function pop(string $queueName)
    {
        $id = null;
        $ttr = null;
        $attempt = null;

        $this->touchIndex($queueName, function (&$data) use (&$id, &$ttr, &$attempt) {
            if (!empty($data['reserved'])) {
                foreach ($data['reserved'] as $key => $payload) {
                    if ($payload[1] + $payload[3] < time()) {
                        list($id, $ttr, $attempt, $time) = $payload;
                        $data['reserved'][$key][2] = ++$attempt;
                        $data['reserved'][$key][3] = time();
                        return;
                    }
                }
            }
            if (!empty($data['delayed']) && $data['delayed'][0][1] <= time()) {
                list($id, $ttr, $time) = array_shift($data['delayed']);
            } elseif (!empty($data['waiting'])) {
                list($id, $ttr) = array_shift($data['waiting']);
            }
            if ($id) {
                $attempt = 1;
                $data['reserved']["job__{$queueName}__{$id}"] = [$id, $ttr, $attempt, time()];
            }
        });

        if ($id) {
            $content = file_get_contents("{$this->path}/job__{$queueName}__{$id}.data");
            $this->delete($queueName, $id);
            return $content;
        }

        return null;
    }

    /**
     * Purge the storage.
     *
     * @param string $queueName
     */
    public function purge(string $queueName)
    {

    }

    /**
     * Release the message.
     * @param string $payload
     * @param string $queueName
     * @param int $delay
     * @return mixed
     */
    public function release(string $payload, string $queueName, $delay = 0)
    {

    }

    /**
     * Delete the message.
     * @param string $queueName
     * @param string $payload
     * @return mixed
     */
    public function delete(string $queueName, string $payload)
    {
        $removed = false;
        $this->touchIndex(function (&$data) use ($payload, &$removed) {
            if (!empty($data['waiting'])) {
                foreach ($data['waiting'] as $key => $_payload) {
                    if ($_payload === $payload) {
                        unset($data['waiting'][$key]);
                        $removed = true;
                        break;
                    }
                }
            }
            if (!$removed && !empty($data['delayed'])) {
                foreach ($data['delayed'] as $key => $_payload) {
                    if ($_payload === $payload) {
                        unset($data['delayed'][$key]);
                        $removed = true;
                        break;
                    }
                }
            }
            if (!$removed && !empty($data['reserved'])) {
                foreach ($data['reserved'] as $key => $_payload) {
                    if ($_payload === $payload) {
                        unset($data['reserved'][$key]);
                        $removed = true;
                        break;
                    }
                }
            }
            if ($removed) {
                unlink("{$this->path}/job__{$queueName}__{$id}.data");
            }
        });
        return $removed;
    }

    /**
     * @param string $queueName
     * @param integer $id
     * @return mixed
     */
    public function status(string $queueName, $id = null)
    {
        if (!is_numeric($id) || $id <= 0) {
            throw new InvalidArgumentException("Unknown message ID: $id.");
        }
        if (file_exists("{$this->path}/job__{$queueName}__{$id}.data")) {
            return self::STATUS_WAITING;
        }
        return self::STATUS_DONE;
    }

    /**
     * @param string $queueName
     * @param callable $callback
     * @throws InvalidConfigException
     */
    private function touchIndex($queueName, $callback)
    {
        $fileName = "$this->path/queue__{$queueName}.data";
        $isNew = !file_exists($fileName);

        touch($fileName);

        if ($isNew && $this->fileMode !== null) {
            chmod($fileName, $this->fileMode);
        }

        if (($file = fopen($fileName, 'rb+')) === false) {
            throw new InvalidConfigException("Unable to open index file: {$fileName}");
        }

        flock($file, LOCK_EX);
        $data = [];
        $content = stream_get_contents($file);

        if ($content !== '') {
            $data = unserialize($content);

        }

        try {
            $callback($data);
            $newContent = serialize($data);
            if ($newContent !== $content) {
                ftruncate($file, 0);
                rewind($file);
                fwrite($file, $newContent);
                fflush($file);
            }
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }

    }

}
