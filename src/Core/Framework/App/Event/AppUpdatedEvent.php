<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class AppUpdatedEvent extends ManifestChangedEvent
{
    public const NAME = 'app.updated';

    public function getName(): string
    {
        return self::NAME;
    }
}
