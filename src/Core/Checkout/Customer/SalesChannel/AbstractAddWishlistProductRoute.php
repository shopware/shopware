<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

abstract class AbstractAddWishlistProductRoute
{
    abstract public function getDecorated(): AbstractAddWishlistProductRoute;

    /**
     * @deprecated tag:v6.4.0 - Parameter $customer will be mandatory in future implementation
     */
    abstract public function add(string $productId, SalesChannelContext $context/*, CustomerEntity $customer*/): SuccessResponse;
}
