<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Currency\Collection\CurrencyBasicCollection;

class CurrencySearchResult extends CurrencyBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
