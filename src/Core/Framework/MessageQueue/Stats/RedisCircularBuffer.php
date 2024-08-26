<?php

namespace Shopware\Core\Framework\MessageQueue\Stats;

class RedisCircularBuffer
{
    public function __construct(private int $bufferSize, private readonly \Redis $redis)
    {
    }

    public function add(string $key, string $value): void
    {
        $this->redis
            ->multi(\Redis::MULTI)
            ->lPush($key, $value)
            ->lTrim($key, 0, $this->bufferSize - 1)
            //->expire($key, 60 * 60)
            ->exec();
    }

    public function get(string $key): array
    {
        return $this->redis->lRange($key, 0, $this->bufferSize - 1);
    }
}
