<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
interface EntitySearcherInterface
{
    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult;
}
