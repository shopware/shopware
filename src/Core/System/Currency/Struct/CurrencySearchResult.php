<?php declare(strict_types=1);

namespace Shopware\System\Currency\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Currency\Collection\CurrencyBasicCollection;

class CurrencySearchResult extends CurrencyBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
