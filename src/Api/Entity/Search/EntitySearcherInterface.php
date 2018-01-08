<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Context\Struct\TranslationContext;

interface EntitySearcherInterface
{
    public function search(string $definition, Criteria $criteria, TranslationContext $context): IdSearchResult;
}
