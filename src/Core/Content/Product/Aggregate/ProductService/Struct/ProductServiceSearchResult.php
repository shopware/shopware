<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductService\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Content\Product\Aggregate\ProductService\Collection\ProductServiceBasicCollection;

class ProductServiceSearchResult extends ProductServiceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
