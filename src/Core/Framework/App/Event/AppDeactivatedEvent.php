<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class AppDeactivatedEvent extends AppChangedEvent
{
    final public const NAME = 'app.deactivated';

    public function getName(): string
    {
        return self::NAME;
    }
}
