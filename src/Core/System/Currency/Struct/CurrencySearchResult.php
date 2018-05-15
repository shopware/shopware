<?php declare(strict_types=1);

namespace Shopware\System\Currency\Struct;

use Shopware\System\Currency\Collection\CurrencyBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CurrencySearchResult extends CurrencyBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
