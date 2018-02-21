<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Read;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Context\Struct\ShopContext;

interface EntityReaderInterface
{
    public function readDetail(string $definition, array $ids, ShopContext $context): EntityCollection;

    public function readBasic(string $definition, array $ids, ShopContext $context): EntityCollection;

    public function readRaw(string $definition, array $ids, ShopContext $context): EntityCollection;
}
