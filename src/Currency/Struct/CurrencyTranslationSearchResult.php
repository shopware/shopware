<?php declare(strict_types=1);

namespace Shopware\Currency\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Currency\Collection\CurrencyTranslationBasicCollection;

class CurrencyTranslationSearchResult extends CurrencyTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
