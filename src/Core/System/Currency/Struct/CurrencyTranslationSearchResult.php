<?php declare(strict_types=1);

namespace Shopware\System\Currency\Struct;

use Shopware\System\Currency\Collection\CurrencyTranslationBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CurrencyTranslationSearchResult extends CurrencyTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
