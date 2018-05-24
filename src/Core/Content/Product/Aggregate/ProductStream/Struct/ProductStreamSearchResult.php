<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductStream\Struct;

use Shopware\Content\Product\Aggregate\ProductStream\Collection\ProductStreamBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ProductStreamSearchResult extends ProductStreamBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
