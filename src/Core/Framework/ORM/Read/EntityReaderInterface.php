<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Read;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityCollection;

interface EntityReaderInterface
{
    public function read(string $definition, ReadCriteria $criteria, Context $context): EntityCollection;

    public function readRaw(string $definition, ReadCriteria $criteria, Context $context): EntityCollection;
}
