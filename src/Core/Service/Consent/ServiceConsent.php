<?php declare(strict_types=1);

namespace Shopware\Core\Service\Consent;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\ServiceConsentRevisionProvider;
use Shopware\Core\System\Consent\ConsentDefinition;
use Shopware\Core\System\Consent\ConsentScope;

/**
 * @internal
 */
#[Package('framework')]
class ServiceConsent implements ConsentDefinition
{
    public const NAME = 'service_consent';

    public function __construct(
        private readonly ServiceConsentRevisionProvider $revisionProvider,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getScopeName(): string
    {
        return ConsentScope\System::NAME;
    }

    public function getSince(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-05-05');
    }

    public function getRequiredPermissions(): array
    {
        return ['system.system_config', 'system.plugin_maintain'];
    }

    public function getLatestRevision(): ?string
    {
        return $this->revisionProvider->getLatestRevision();
    }
}
