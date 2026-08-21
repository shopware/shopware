<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization\Ownership;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class Ownership
{
    public function __construct(
        public readonly string $webhookId,
        public readonly ?string $appId,
    ) {
    }

    public function belongsToApp(): bool
    {
        return $this->appId !== null;
    }
}
