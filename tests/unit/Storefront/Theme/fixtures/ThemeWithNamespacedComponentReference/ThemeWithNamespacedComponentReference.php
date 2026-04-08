<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithNamespacedComponentReference;

use Shopware\Core\Framework\Bundle;
use Shopware\Storefront\Framework\ThemeInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
class ThemeWithNamespacedComponentReference extends Bundle implements ThemeInterface
{
    public function getThemeName(): string
    {
        return 'ThemeWithNamespacedComponentReference';
    }

    public function getPath(): string
    {
        return __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
    }
}
