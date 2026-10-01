<?php declare(strict_types=1);

namespace Shopware\Core\Service\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('framework')]
class CommercialLicenseProvidedEvent extends Event implements Hookable
{
    final public const NAME = 'commercial_license.provided';

    private function __construct(
        private readonly string $licenseKey,
        private readonly string $licenseHost,
        private readonly ?string $serviceAppId = null,
    ) {
    }

    public static function forAll(string $licenseKey, string $licenseHost): self
    {
        return new self($licenseKey, $licenseHost);
    }

    public static function forService(string $serviceAppId, string $licenseKey, string $licenseHost): self
    {
        return new self($licenseKey, $licenseHost, $serviceAppId);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return array{licenseKey: string, licenseHost: string}
     */
    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'licenseKey' => $this->licenseKey,
            'licenseHost' => $this->licenseHost,
        ];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $this->serviceAppId === null || $this->serviceAppId === $appId;
    }
}
