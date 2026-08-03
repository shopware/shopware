<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Event;

use Shopware\Core\Framework\Log\Package;

/**
 * Dispatched after the downloaded translation files and metadata entry for a locale have been removed.
 */
#[Package('discovery')]
class TranslationRemovedEvent
{
    public function __construct(
        private readonly string $locale,
    ) {
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
