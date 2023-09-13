<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Redis;

class RedisStub extends \Redis
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct()
    {
    }

    /**
     * @param mixed $context
     */
    public function connect($host, $port = 6379, $timeout = 0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0, $context = null)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        if (\array_key_exists($key, $this->data)) {
            $value = $this->data[$key];

            if ($value['expire'] !== 0 && $value['expire'] < time()) {
                unset($this->data[$key]);

                return false;
            }

            return $value['value'];
        }

        return false;
    }

    /**
     * @param string $key
     * @param string $value
     * @param int|array{'EX'?: int, 'ex'?: int} $options
     */
    public function set($key, $value, $options = 0)
    {
        $expire = 0;

        if (\is_array($options)) {
            if (isset($options['ex'])) {
                $expire = time() + $options['ex'];
            }

            if (isset($options['EX'])) {
                $expire = time() + $options['EX'];
            }
        } elseif (\is_int($options)) {
            $expire = time() + $options;
        }

        $this->data[$key] = ['value' => $value, 'expire' => $expire];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setex($key, $expire, $value)
    {
        return $this->set($key, $value, $expire);
    }

    /**
     * @param string $key1
     * @param string ...$otherKeys
     *
     * @return int
     */
    public function del($key1, ...$otherKeys)
    {
        $deletions = 0;

        $otherKeys[] = $key1;

        foreach ($otherKeys as $key) {
            if (\array_key_exists($key, $this->data)) {
                unset($this->data[$key]);
                ++$deletions;
            }
        }

        return $deletions;
    }

    public function exists($key, ...$other_keys)
    {
        if ($other_keys === []) {
            return \array_key_exists($key, $this->data);
        }

        $keys = array_merge([$key], $other_keys);

        $found = 0;

        foreach ($keys as $keyLoop) {
            if (\array_key_exists($keyLoop, $this->data)) {
                ++$found;
            }
        }

        return $found;
    }

    public function sAdd($key, $value, ...$other_values)
    {
        $current = $this->get($key);

        if ($current === false) {
            $current = [];
        }

        if (!\is_array($current)) {
            throw new \RedisException('sAdd can be only called on a set');
        }

        $current = array_merge($current, [$value], $other_values);
        $current = array_unique($current);

        sort($current);

        $this->data[$key] = ['value' => $current, 'expire' => $current];

        return true;
    }

    /**
     * @param string $key
     *
     * @return list<string>
     */
    public function sMembers($key)
    {
        /** @var list<string>|false|string $value */
        $value = $this->get($key);

        if ($value === false) {
            return [];
        }

        if (!\is_array($value)) {
            throw new \RedisException('sMembers can be only called on a set');
        }

        return $value;
    }

    /**
     * @return RedisMultiWrapper
     *
     * @phpstan-ignore-next-line
     */
    public function multi($mode = \Redis::MULTI)
    {
        return new RedisMultiWrapper($this);
    }

    /**
     * {@inheritdoc}
     */
    public function ttl($key)
    {
        if (\array_key_exists($key, $this->data)) {
            $value = $this->data[$key];

            // If the expiry is 0, the key will never expire
            if ($value['expire'] === 0) {
                return -1;
            }

            return $value['expire'] - time();
        }

        return false;
    }
}
