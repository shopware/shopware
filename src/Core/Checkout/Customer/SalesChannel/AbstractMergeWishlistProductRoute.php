<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * This route can be used to merge wishlist products from guest users to registered users.
 */
abstract class AbstractMergeWishlistProductRoute
{
    abstract public function getDecorated(): AbstractMergeWishlistProductRoute;

    /**
     * @deprecated tag:v6.4.0 - Parameter $customer will be mandatory in future implementation
     */
    abstract public function merge(RequestDataBag $data, SalesChannelContext $context/*, CustomerEntity $customer*/): SuccessResponse;
}
