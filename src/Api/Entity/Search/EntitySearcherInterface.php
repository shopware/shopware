<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Context\Struct\ShopContext;

interface EntitySearcherInterface
{
    public function search(string $definition, Criteria $criteria, ShopContext $context): IdSearchResult;
}
