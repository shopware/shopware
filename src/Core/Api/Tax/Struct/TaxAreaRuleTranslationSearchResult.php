<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Tax\Collection\TaxAreaRuleTranslationBasicCollection;

class TaxAreaRuleTranslationSearchResult extends TaxAreaRuleTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
