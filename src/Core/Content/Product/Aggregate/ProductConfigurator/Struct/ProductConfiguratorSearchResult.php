<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Struct;

use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Collection\ProductConfiguratorBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ProductConfiguratorSearchResult extends ProductConfiguratorBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
