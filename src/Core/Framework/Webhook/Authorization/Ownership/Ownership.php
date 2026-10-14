<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization\Ownership;

use Shopware\Core\Framework\Api\Acl\ApiCallerType;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
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
        public readonly ?string $ownerId,
    ) {
    }

    public function belongsToApp(): bool
    {
        return $this->appId !== null;
    }

    public function isOwnedBy(AdminApiSource $source): bool
    {
        return $this->ownerId !== null && $this->ownerId === ApiCallerType::of($source)->idOf($source);
    }
}
