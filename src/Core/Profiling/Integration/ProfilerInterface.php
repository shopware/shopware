<?php

namespace Shopware\Core\Profiling\Integration;

/**
 * @internal experimental atm
 */
interface ProfilerInterface
{
    public function trace(string $title, \Closure $closure, string $category = 'shopware');
}
