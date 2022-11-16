<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @deprecated tag:v6.5.0 - Call the AbstractPropertyGroupSorter, AbstractProductMaxPurchaseCalculator, AbstractIsNewDetector by using the respective services instead.
 *
 * @package inventory
 */
abstract class AbstractSalesChannelProductBuilder
{
    abstract public function getDecorated(): AbstractSalesChannelProductBuilder;

    abstract public function build(
        SalesChannelProductEntity $product,
        SalesChannelContext $context
    ): void;
}
