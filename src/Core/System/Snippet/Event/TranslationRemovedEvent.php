<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after the downloaded translation files and metadata entry for a locale have been removed.
 */
#[Package('discovery')]
class TranslationRemovedEvent extends Event
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
