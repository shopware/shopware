<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Read;

use Shopware\Framework\Context;
use Shopware\Framework\ORM\EntityCollection;

interface EntityReaderInterface
{
    public function readDetail(string $definition, array $ids, Context $context): EntityCollection;

    public function readBasic(string $definition, array $ids, Context $context): EntityCollection;

    public function readRaw(string $definition, array $ids, Context $context): EntityCollection;
}
