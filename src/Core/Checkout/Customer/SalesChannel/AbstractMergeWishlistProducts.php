<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * This route can be used to merge wishlist products from guest users to registered users.
 */
abstract class AbstractMergeWishlistProducts
{
    abstract public function getDecorated(): AbstractMergeWishlistProducts;

    abstract public function merge(RequestDataBag $data, SalesChannelContext $context): SuccessResponse;
}
