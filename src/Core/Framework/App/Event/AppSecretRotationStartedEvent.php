<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppSecretRotationStartedEvent extends AppSecretRotationEvent
{
    final public const NAME = 'app.secret_rotation.started';

    public function getName(): string
    {
        return self::NAME;
    }
}
