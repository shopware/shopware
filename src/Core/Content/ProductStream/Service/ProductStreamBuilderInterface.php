<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
interface ProductStreamBuilderInterface
{
    /**
     * @return array<int, Filter>
     */
    public function buildFilters(
        string $id,
        Context $context
    ): array;
}
