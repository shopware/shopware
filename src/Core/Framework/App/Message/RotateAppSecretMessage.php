<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @codeCoverageIgnore
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
class RotateAppSecretMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly string $appId,
        private readonly string $trigger
    ) {
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function getTrigger(): string
    {
        return $this->trigger;
    }
}
