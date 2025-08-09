<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck\Util;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<SalesChannelDomain>
 */
#[Package('framework')]
class SalesChannelDomainCollection extends Collection
{
    /**
     * @param list<SalesChannelDomain> $elements
     */
    public function __construct(
        array $elements,
    ) {
        $indexed = [];
        foreach ($elements as $element) {
            $indexed[$element->salesChannelId] = $element;
        }

        parent::__construct($indexed);
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelDomain::class;
    }
}
