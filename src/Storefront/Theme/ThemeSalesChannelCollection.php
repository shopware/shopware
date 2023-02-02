<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ThemeSalesChannel>
 */
class ThemeSalesChannelCollection extends Collection
{
    /**
     * @var ThemeSalesChannel[]
     */
    protected $elements = [];

    public function getExpectedClass(): string
    {
        return ThemeSalesChannel::class;
    }
}
