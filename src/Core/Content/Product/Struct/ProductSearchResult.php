<?php declare(strict_types=1);

namespace Shopware\Content\Product\Struct;

use Shopware\Content\Product\Collection\ProductBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ProductSearchResult extends ProductBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
