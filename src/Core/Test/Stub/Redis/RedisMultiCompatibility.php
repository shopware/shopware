<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Redis;

if (version_compare(phpversion('redis') ?: '0.0.0', '6.0.0', '>=')) {
    trait RedisMultiCompatibility
    {
        public function del(array|string $key, string ...$other_keys): \Redis|int|false
        {
            return $this->doCall('del', [$key, ...$other_keys]);
        }

        public function sMembers(string $key): RedisMultiWrapper
        {
            return $this->doCall('sMembers', [$key]);
        }
    }
} else {
    trait RedisMultiCompatibility
    {
        public function del($key1, ...$otherKeys)
        {
            return $this->doCall('del', [$key1, ...$otherKeys]);
        }

        public function sMembers($key)
        {
            return $this->doCall('sMembers', [$key]);
        }
    }
}
