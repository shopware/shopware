<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
interface ProductSearchTermInterpreterInterface
{
    public function interpret(string $word, Context $context): SearchPattern;
}
