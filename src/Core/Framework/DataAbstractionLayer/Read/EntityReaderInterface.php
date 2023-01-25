<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Read;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
interface EntityReaderInterface
{
    /**
     * @return EntityCollection<Entity>
     */
    public function read(EntityDefinition $definition, Criteria $criteria, Context $context): EntityCollection;
}
