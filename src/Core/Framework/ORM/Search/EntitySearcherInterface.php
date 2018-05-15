<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search;

use Shopware\Application\Context\Struct\ApplicationContext;

interface EntitySearcherInterface
{
    public function search(string $definition, Criteria $criteria, ApplicationContext $context): IdSearchResult;
}
