<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Read;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

interface EntityReaderInterface
{
    public function read(string $definition, ReadCriteria $criteria, Context $context): EntityCollection;
}
