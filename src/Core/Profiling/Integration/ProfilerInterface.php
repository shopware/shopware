<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Integration;

/**
 * @internal experimental atm
 */
interface ProfilerInterface
{
    /**
     * @return mixed
     */
    public function trace(string $title, \Closure $closure, string $category, array $tags);
}
