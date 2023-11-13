<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class AppActivatedEvent extends AppChangedEvent
{
    final public const NAME = 'app.activated';

    public function getName(): string
    {
        return self::NAME;
    }
}
