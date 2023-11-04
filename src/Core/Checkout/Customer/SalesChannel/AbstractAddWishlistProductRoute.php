<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

#[Package('customer-order')]
abstract class AbstractAddWishlistProductRoute
{
    abstract public function getDecorated(): AbstractAddWishlistProductRoute;

    abstract public function add(string $productId, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse;
}
