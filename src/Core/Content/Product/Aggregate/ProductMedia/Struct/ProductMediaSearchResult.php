<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductMedia\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Content\Product\Aggregate\ProductMedia\Collection\ProductMediaBasicCollection;

class ProductMediaSearchResult extends ProductMediaBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
