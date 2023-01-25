<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class CountryRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
}
