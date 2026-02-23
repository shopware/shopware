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

    public function connect(
        string $host,
        int $port = 6379,
        float $timeout = 0,
        ?string $persistent_id = null,
        int $retry_interval = 0,
        float $read_timeout = 0,
        ?array $context = null
    ): bool {
        return true;
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function get(string $key): mixed
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

    public function set(string $key, mixed $value, mixed $options = null): \Redis|string|bool
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

    public function del(array|string $key, string ...$other_keys): \Redis|int|false
    {
        $deletions = 0;

        if (\is_string($key)) {
            $other_keys[] = $key;
        } else {
            $other_keys = array_merge($key, $other_keys);
        }

        foreach ($other_keys as $otherKey) {
            if (\array_key_exists($otherKey, $this->data)) {
                unset($this->data[$otherKey]);
                ++$deletions;
            }
        }

        return $deletions;
    }

    public function exists(mixed $key, mixed ...$other_keys): \Redis|int|bool
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

    public function sAdd(string $key, mixed $value, mixed ...$other_values): \Redis|int|false
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

        return 1;
    }

    public function sMembers(string $key): \Redis|array|false
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

    public function ttl(string $key): \Redis|int|false
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

    public function multi(int $value = \Redis::MULTI): \Redis|bool
    {
        return new RedisMultiWrapper($this);
    }
}
