<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Log\Package;
/**
 * @package core
 */
#[Package('core')]
class AppDeactivatedEvent extends AppChangedEvent
{
    public const NAME = 'app.deactivated';

    public function getName(): string
    {
        return self::NAME;
    }
}
