<?php declare(strict_types=1);

namespace Shopware\Tax\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Tax\Collection\TaxAreaRuleTranslationBasicCollection;

class TaxAreaRuleTranslationSearchResult extends TaxAreaRuleTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
