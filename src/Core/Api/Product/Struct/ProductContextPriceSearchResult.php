<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Product\Collection\ProductContextPriceBasicCollection;

class ProductContextPriceSearchResult extends ProductContextPriceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
