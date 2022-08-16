<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Read;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
interface EntityReaderInterface
{
    /**
     * @return EntityCollection<Entity>
     */
    public function read(EntityDefinition $definition, Criteria $criteria, Context $context): EntityCollection;
}
