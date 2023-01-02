<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;

/**
 * @package checkout
 */
#[Package('checkout')]
class PaymentMethodRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
}
