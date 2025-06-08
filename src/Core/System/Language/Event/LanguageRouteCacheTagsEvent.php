<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@discovery')]
/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0 as it was not used anymore
 */
class LanguageRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
}
