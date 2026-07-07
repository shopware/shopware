<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * Enriches a criteria with a product stream's filters and grouping state.
 */
#[Package('inventory')]
abstract class AbstractProductStreamBuilder
{
    abstract public function enrichCriteria(Criteria $criteria, string $id, Context $context): void;
}
