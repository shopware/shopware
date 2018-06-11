<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search;

use Shopware\Core\Framework\Context;

interface EntitySearcherInterface
{
    public function search(string $definition, Criteria $criteria, Context $context): IdSearchResult;
}
