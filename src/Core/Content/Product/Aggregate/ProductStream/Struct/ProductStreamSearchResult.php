<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductStream\Struct;

use Shopware\Core\Content\Product\Aggregate\ProductStream\Collection\ProductStreamBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ProductStreamSearchResult extends ProductStreamBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
