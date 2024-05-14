<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class CurrencyRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
}
