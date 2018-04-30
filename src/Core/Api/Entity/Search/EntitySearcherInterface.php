<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Context\Struct\ApplicationContext;

interface EntitySearcherInterface
{
    public function search(string $definition, Criteria $criteria, ApplicationContext $context): IdSearchResult;
}
