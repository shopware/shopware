<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Product\Collection\ProductServiceBasicCollection;

class ProductServiceSearchResult extends ProductServiceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
