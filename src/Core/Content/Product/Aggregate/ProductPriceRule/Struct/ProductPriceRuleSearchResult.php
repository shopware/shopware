<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Struct;

use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Collection\ProductPriceRuleBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ProductPriceRuleSearchResult extends ProductPriceRuleBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
