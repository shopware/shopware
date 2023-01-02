<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;

/**
 * @package system-settings
 */
#[Package('system-settings')]
class LanguageRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
}
