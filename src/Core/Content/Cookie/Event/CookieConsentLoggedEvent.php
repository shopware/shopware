<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Event;

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
     * @param list<string> $acceptedGroups technical names of the accepted cookie groups
     */
    public function __construct(
        public string $consentAction,
        public array $acceptedGroups,
        public string $configHash,
        public string $salesChannelId,
        public string $languageId,
    ) {
    }
}
