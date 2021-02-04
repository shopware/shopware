<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class AppActivatedEvent extends AppChangedEvent
{
    public const NAME = 'app.activated';

    public function getName(): string
    {
        return self::NAME;
    }
}
