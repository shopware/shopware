<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<DomainStruct>
 */
#[Package('framework')]
class DomainCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'storefront_domain_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return DomainStruct::class;
    }
}
