<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Log\Package;

/**
 * @template TCachedContent
 */
#[Package('core')]
abstract class AbstractCacheTracer
{
    /**
     * @return AbstractCacheTracer<TCachedContent>
     */
    abstract public function getDecorated(): AbstractCacheTracer;

    /**
     * @template TReturn of TCachedContent
     *
     * @param \Closure(): TReturn $param
     *
     * @return TReturn
     */
    abstract public function trace(string $key, \Closure $param);

    /**
     * @return array<string>
     */
    abstract public function get(string $key): array;
}
