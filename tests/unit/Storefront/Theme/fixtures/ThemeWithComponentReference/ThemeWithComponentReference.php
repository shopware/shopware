<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithComponentReference;

use Shopware\Core\Framework\Bundle;
use Shopware\Storefront\Framework\ThemeInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
class ThemeWithComponentReference extends Bundle implements ThemeInterface
{
    public function getThemeName(): string
    {
        return 'ThemeWithComponentReference';
    }

    public function getPath(): string
    {
        return __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
    }
}
