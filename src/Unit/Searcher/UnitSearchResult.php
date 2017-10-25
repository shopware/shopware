<?php declare(strict_types=1);

namespace Shopware\Unit\Searcher;

use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;
use Shopware\Unit\Struct\UnitBasicCollection;

class UnitSearchResult extends UnitBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
