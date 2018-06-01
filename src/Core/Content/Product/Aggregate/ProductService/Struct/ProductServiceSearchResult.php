<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductService\Struct;

use Shopware\Core\Content\Product\Aggregate\ProductService\Collection\ProductServiceBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ProductServiceSearchResult extends ProductServiceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
