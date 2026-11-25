<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface Projection
{
    /**
     * Add the select under the usual root.property alias, or omit it for this read.
     */
    public function project(?Criteria $criteria, EntityDefinition $definition, Field $field, string $root, QueryBuilder $query): void;
}
