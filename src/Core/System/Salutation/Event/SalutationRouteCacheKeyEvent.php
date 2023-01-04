<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
class SalutationRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
}
