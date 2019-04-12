<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

interface PluginInterface
{
    public function isActive(): bool;

    public function registerBundles(): \Generator;
}
