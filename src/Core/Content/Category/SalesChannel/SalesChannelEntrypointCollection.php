<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<SalesChannelEntrypointStruct>
 */
#[Package('inventory')]
class SalesChannelEntrypointCollection extends Collection
{
    /**
     * @return array|string[]
     */
    public function getFlat(): array
    {
        return $this->map(fn (SalesChannelEntrypointStruct $entrypoint) => $entrypoint->getCategoryId());
    }

    protected function getExpectedClass(): ?string
    {
        return SalesChannelEntrypointStruct::class;
    }
}
