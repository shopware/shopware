<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Hook;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;

/**
 * @internal Not intended for use in plugins
 * Can be implemented by hooks to provide services with the sales channel context.
 * The services can inject the context beforehand and provide a narrow API to the developer.
 */
#[Package('checkout')]
interface CartAware extends SalesChannelContextAware
{
    public function getCart(): Cart;
}
