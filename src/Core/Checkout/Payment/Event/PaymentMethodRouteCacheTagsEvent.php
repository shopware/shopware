<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PaymentMethodRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
}
