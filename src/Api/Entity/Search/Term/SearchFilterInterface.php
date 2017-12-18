<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search\Term;

use Shopware\Context\Struct\TranslationContext;

interface SearchFilterInterface
{
    public function filter(array $tokens, TranslationContext $context): array;
}
