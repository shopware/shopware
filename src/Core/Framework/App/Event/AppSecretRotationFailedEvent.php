<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppSecretRotationFailedEvent extends AppSecretRotationEvent
{
    final public const NAME = 'app.secret_rotation.failed';

    public function __construct(
        AppEntity $app,
        Context $context,
        string $trigger,
        private readonly \Throwable $exception
    ) {
        parent::__construct($app, $context, $trigger);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }
}
