<?php

namespace Shopware\Core\Profiling\Integration;

interface ProfilerInterface
{
    public static function trace(string $title, \Closure $closure, string $category = 'shopware');
}