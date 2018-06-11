<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationBasicCollection;

class CurrencyTranslationSearchResult extends CurrencyTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
