<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * Dispatched after the translations for a locale have been downloaded and installed.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class TranslationLoadedEvent implements ShopwareEvent
{
    public function __construct(
        private readonly string $locale,
        private readonly Context $context,
    ) {
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
