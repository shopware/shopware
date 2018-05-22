<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductService\Struct;

use Shopware\Content\Product\Aggregate\ProductService\Collection\ProductServiceBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ProductServiceSearchResult extends ProductServiceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
