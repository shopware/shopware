<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class LanguageRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
}
