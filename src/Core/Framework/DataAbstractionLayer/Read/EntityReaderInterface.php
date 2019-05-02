<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Read;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

interface EntityReaderInterface
{
    public function read(EntityDefinition $definition, Criteria $criteria, Context $context): EntityCollection;
}
