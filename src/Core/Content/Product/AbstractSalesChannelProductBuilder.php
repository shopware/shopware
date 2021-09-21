<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractSalesChannelProductBuilder
{
    abstract public function getDecorated(): AbstractSalesChannelProductBuilder;

    abstract public function build(
        SalesChannelProductEntity $product,
        SalesChannelContext $context
    ): void;
}
