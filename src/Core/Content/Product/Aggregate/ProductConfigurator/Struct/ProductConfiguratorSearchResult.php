<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductConfigurator\Struct;

use Shopware\Content\Product\Aggregate\ProductConfigurator\Collection\ProductConfiguratorBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ProductConfiguratorSearchResult extends ProductConfiguratorBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
