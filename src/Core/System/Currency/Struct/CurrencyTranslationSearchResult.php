<?php declare(strict_types=1);

namespace Shopware\System\Currency\Struct;

use Shopware\System\Currency\Collection\CurrencyTranslationBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class CurrencyTranslationSearchResult extends CurrencyTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
