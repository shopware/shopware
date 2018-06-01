<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search;

use Shopware\Framework\Context;

interface EntitySearcherInterface
{
    public function search(string $definition, Criteria $criteria, Context $context): IdSearchResult;
}
