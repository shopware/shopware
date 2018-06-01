<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search\Term;

use Shopware\Framework\Context;

interface SearchFilterInterface
{
    public function filter(array $tokens, Context $context): array;
}
