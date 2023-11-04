<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
interface ProductStreamBuilderInterface
{
    public function buildFilters(
        string $id,
        Context $context
    ): array;
}
