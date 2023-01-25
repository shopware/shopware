<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be used to delete the entire cart
 */
#[Package('checkout')]
abstract class AbstractCartDeleteRoute
{
    abstract public function getDecorated(): AbstractCartDeleteRoute;

    abstract public function delete(SalesChannelContext $context): NoContentResponse;
}
