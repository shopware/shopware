<?php declare(strict_types=1);

namespace Shopware\Tax\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Tax\Collection\TaxAreaRuleTranslationBasicCollection;

class TaxAreaRuleTranslationSearchResult extends TaxAreaRuleTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
