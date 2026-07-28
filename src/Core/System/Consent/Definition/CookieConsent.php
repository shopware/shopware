<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Definition;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentDefinition;
use Shopware\Core\System\Consent\ConsentScope;

/**
 * Consent of storefront visitors to the cookie banner.
 *
 * Revisions are tracked via the `cookie_consent_config_version` table
 * (one snapshot per cookie configuration hash), not through the consent
 * system's revision mechanism, hence getLatestRevision() returns null.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('data-services')]
class CookieConsent implements ConsentDefinition
{
    public const NAME = 'cookie_consent';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getScopeName(): string
    {
        return ConsentScope\StorefrontVisitor::NAME;
    }

    public function getSince(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-07-13');
    }

    public function getRequiredPermissions(): array
    {
        return [];
    }

    public function getLatestRevision(): ?string
    {
        return null;
    }
}
