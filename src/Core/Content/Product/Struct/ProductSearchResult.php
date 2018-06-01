<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Struct;

use Shopware\Core\Content\Product\Collection\ProductBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ProductSearchResult extends ProductBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
