<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ThemeSalesChannel>
 */
#[Package('framework')]
class ThemeSalesChannelCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return ThemeSalesChannel::class;
    }
}
