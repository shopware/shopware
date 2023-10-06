<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ThemeSalesChannel>
 */
#[Package('storefront')]
class ThemeSalesChannelCollection extends Collection
{
    /**
     * @var ThemeSalesChannel[]
     */
    protected $elements = [];

    protected function getExpectedClass(): string
    {
        return ThemeSalesChannel::class;
    }
}
