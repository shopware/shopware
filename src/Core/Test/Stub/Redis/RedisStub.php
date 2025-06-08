<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Redis;

class RedisStub extends \Redis
{
    use RedisCompatibility;

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct()
    {
    }

    private function doGet(string $key): mixed
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

    private function doSet(string $key, mixed $value, mixed $options = null): bool
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

    private function doDel(string $key, string ...$other_keys): int
    {
        $deletions = 0;

        $other_keys[] = $key;

        foreach ($other_keys as $otherKey) {
            if (\array_key_exists($otherKey, $this->data)) {
                unset($this->data[$otherKey]);
                ++$deletions;
            }
        }

        return $deletions;
    }

    private function doExists(mixed $key, mixed ...$other_keys): int|bool
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

    private function doSAdd(string $key, mixed $value, mixed ...$other_values): int
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

    /**
     * @return list<string>
     */
    private function doSMembers(string $key): array
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

    private function doTtl(string $key): int|false
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
