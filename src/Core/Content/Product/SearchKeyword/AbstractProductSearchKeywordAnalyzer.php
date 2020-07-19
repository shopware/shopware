<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

abstract class AbstractProductSearchKeywordAnalyzer implements ProductSearchKeywordAnalyzerInterface
{
    abstract public function extendCriteria(Criteria $criteria): void;
}
