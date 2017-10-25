<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Searcher;

use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;
use Shopware\TaxAreaRule\Struct\TaxAreaRuleBasicCollection;

class TaxAreaRuleSearchResult extends TaxAreaRuleBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
