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
    public const USER = 'user';
    public const INTEGRATION = 'integration';

    /**
     * @param self::USER|self::INTEGRATION $type
     */
    private function __construct(
        public string $type,
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
            return new self(self::INTEGRATION, $integrationId);
        }

        $userId = $source->getUserId();

        return $userId !== null ? new self(self::USER, $userId) : null;
    }
}
