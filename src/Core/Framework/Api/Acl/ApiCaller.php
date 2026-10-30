<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ApiCaller
{
    /**
     * @param IdentityType::User|IdentityType::Integration $type the caller is never resolved as an app
     */
    private function __construct(
        public IdentityType $type,
        public string $id,
    ) {
    }

    /**
     * When an app sends the `sw-app-user-id` header, the caller is the app, not that user.
     */
    public static function of(ContextSource $source): ?self
    {
        if (!$source instanceof AdminApiSource) {
            return null;
        }

        $integrationId = $source->getIntegrationId();
        if ($integrationId !== null) {
            return new self(IdentityType::Integration, $integrationId);
        }

        $userId = $source->getUserId();

        return $userId !== null ? new self(IdentityType::User, $userId) : null;
    }
}
