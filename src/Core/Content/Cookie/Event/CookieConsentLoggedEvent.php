<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Event;

use Shopware\Core\Content\Cookie\CookieConsentLog\CookieConsentLogEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * Dispatched whenever a storefront visitor's cookie consent decision was logged.
 *
 * Intentionally separate from the admin consent events
 * (ConsentAcceptedEvent / ConsentRevokedEvent): it is anonymous, high-volume
 * and does not write to the `consent_log` table.
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
readonly class CookieConsentLoggedEvent
{
    /**
     * @param array<string, CookieConsentLogEntity::DECISION_*> $groupDecisions verdict per cookie group, keyed by technical name
     * @param list<string> $acceptedCookies names of the accepted cookies that required consent
     * @param string $serverConfigHash hash of the configuration the server held while logging
     * @param string|null $renderedConfigHash unverified hash of the configuration the client displayed, null if none was displayed
     */
    public function __construct(
        public string $consentAction,
        public array $groupDecisions,
        public array $acceptedCookies,
        public string $serverConfigHash,
        public ?string $renderedConfigHash,
        public string $salesChannelId,
        public string $languageId,
    ) {
    }
}
