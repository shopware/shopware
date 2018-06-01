<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search\Term;

use Shopware\Core\Framework\Context;

interface SearchFilterInterface
{
    public function filter(array $tokens, Context $context): array;
}
