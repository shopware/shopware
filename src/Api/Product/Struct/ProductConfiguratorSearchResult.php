<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Product\Collection\ProductConfiguratorBasicCollection;

class ProductConfiguratorSearchResult extends ProductConfiguratorBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
