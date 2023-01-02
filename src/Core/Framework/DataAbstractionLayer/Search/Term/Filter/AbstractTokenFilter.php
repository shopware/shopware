<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class AbstractTokenFilter
{
    abstract public function getDecorated(): AbstractTokenFilter;

    abstract public function filter(array $tokens, Context $context): array;
}
