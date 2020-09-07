<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

class AppActivatedEvent extends AppChangedEvent
{
    public const NAME = 'app.activated';

    public function getName(): string
    {
        return self::NAME;
    }
}
