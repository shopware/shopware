<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Redis;

if (version_compare(phpversion('redis') ?: '0.0.0', '6.0.0', '>=')) {
    trait RedisCompatibility
    {
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
            return $this->doGet($key);
        }

        public function set(string $key, mixed $value, mixed $options = null): \Redis|string|bool
        {
            return $this->doSet($key, $value, $options);
        }

        public function del(array|string $key, string ...$other_keys): \Redis|int|false
        {
            return $this->doDel($key, ...$other_keys);
        }

        public function exists(mixed $key, mixed ...$other_keys): \Redis|int|bool
        {
            return $this->doExists($key, ...$other_keys);
        }

        public function sAdd(string $key, mixed $value, mixed ...$other_values): \Redis|int|false
        {
            return $this->doSAdd($key, $value, ...$other_values);
        }

        public function sMembers(string $key): \Redis|array|false
        {
            return $this->doSMembers($key);
        }

        public function ttl(string $key): \Redis|int|false
        {
            return $this->doTtl($key);
        }

        public function multi(int $value = \Redis::MULTI): \Redis|bool
        {
            return new RedisMultiWrapper($this);
        }
    }
} else {
    trait RedisCompatibility
    {
        public function connect(
            $host,
            $port = 6379,
            $timeout = 0,
            $persistent_id = null,
            $retry_interval = 0,
            $read_timeout = 0,
            $context = null
        ) {
            return true;
        }

        public function isConnected()
        {
            return true;
        }

        public function get($key)
        {
            return $this->doGet($key);
        }

        public function set($key, $value, $options = 0)
        {
            return $this->doSet($key, $value, $options);
        }

        public function del($key1, ...$otherKeys)
        {
            return $this->doDel($key1, ...$otherKeys);
        }

        public function exists($key, ...$other_keys)
        {
            return $this->doExists($key, ...$other_keys);
        }

        public function sAdd($key, $value, ...$other_values)
        {
            return $this->doSAdd($key, $value, ...$other_values);
        }

        public function sMembers($key)
        {
            return $this->doSMembers($key);
        }

        public function ttl($key)
        {
            return $this->doTtl($key);
        }

        public function multi($mode = \Redis::MULTI)
        {
            return new RedisMultiWrapper($this);
        }
    }
}
