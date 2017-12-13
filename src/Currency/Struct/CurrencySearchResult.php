<?php declare(strict_types=1);

namespace Shopware\Currency\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Currency\Collection\CurrencyBasicCollection;

class CurrencySearchResult extends CurrencyBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
