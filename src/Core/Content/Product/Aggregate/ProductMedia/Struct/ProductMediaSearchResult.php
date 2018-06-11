<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductMedia\Struct;

use Shopware\Core\Content\Product\Aggregate\ProductMedia\Collection\ProductMediaBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ProductMediaSearchResult extends ProductMediaBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
