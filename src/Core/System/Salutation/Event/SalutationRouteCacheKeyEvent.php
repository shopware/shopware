<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0 as it was not used anymore
 */
class SalutationRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
}
