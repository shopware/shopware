<?php declare(strict_types=1);

namespace Shopware\Api\Currency\Struct;

use Shopware\Api\Currency\Collection\CurrencyBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CurrencySearchResult extends CurrencyBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
