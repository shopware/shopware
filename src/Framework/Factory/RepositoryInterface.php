<?php declare(strict_types=1);

namespace Shopware\Framework\Factory;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;

interface RepositoryInterface
{
    public function searchUuids(Criteria $criteria, TranslationContext $context): UuidSearchResult;
}
