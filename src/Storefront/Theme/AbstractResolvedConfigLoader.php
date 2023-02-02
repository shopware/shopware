<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractResolvedConfigLoader
{
    abstract public function getDecorated(): AbstractResolvedConfigLoader;

    abstract public function load(string $themeId, SalesChannelContext $context): array;
}
