<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
abstract class AppSecretRotationEvent extends AppChangedEvent
{
    public function __construct(
        AppEntity $app,
        Context $context,
        private readonly string $trigger
    ) {
        parent::__construct($app, $context);
    }

    public function getTrigger(): string
    {
        return $this->trigger;
    }
}
