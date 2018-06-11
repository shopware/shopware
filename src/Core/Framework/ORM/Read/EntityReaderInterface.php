<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Read;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityCollection;

interface EntityReaderInterface
{
    public function readBasic(string $definition, array $ids, Context $context): EntityCollection;

    public function readRaw(string $definition, array $ids, Context $context): EntityCollection;
}
