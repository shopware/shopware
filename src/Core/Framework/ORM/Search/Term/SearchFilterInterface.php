<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search\Term;

use Shopware\Application\Context\Struct\ApplicationContext;

interface SearchFilterInterface
{
    public function filter(array $tokens, ApplicationContext $context): array;
}
