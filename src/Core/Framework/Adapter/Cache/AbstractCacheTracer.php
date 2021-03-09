<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

abstract class AbstractCacheTracer
{
    abstract public function getDecorated(): AbstractCacheTracer;

    abstract public function trace(string $key, \Closure $param);

    abstract public function get(string $key): array;
}
