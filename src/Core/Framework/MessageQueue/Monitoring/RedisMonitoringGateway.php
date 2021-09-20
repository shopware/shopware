<?php

namespace Shopware\Core\Framework\MessageQueue\Monitoring;

use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class RedisMonitoringGateway extends AbstractMonitoringGateway
{
    private const PREFIX = 'queue-monitoring:';

    private \Redis $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    public function getDecorated(): AbstractMonitoringGateway
    {
        throw new DecorationPatternException(self::class);
    }

    public function increment(string $name): void
    {
        $this->redis->incr(self::PREFIX . $name);
    }

    public function decrement(string $name): void
    {
        $this->redis->decr(self::PREFIX . $name,);
    }

    public function get(): array
    {
        $keys = $this->redis->keys(self::PREFIX . '*');

        $rows = $this->redis->mget($keys);

        $result = [];
        foreach ($keys as $index => $key) {
            $result[] = ['name' => str_replace(self::PREFIX, '', $key), 'size' => max(0, (int) $rows[$index])];
        }

        return $result;
    }
}
