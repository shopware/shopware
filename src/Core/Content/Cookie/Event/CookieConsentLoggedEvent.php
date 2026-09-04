<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Event;

use Shopware\Core\Content\Cookie\CookieConsentLog\CookieConsentLogDefinition;
use Shopware\Core\Content\Cookie\CookieConsentLog\CookieConsentLogEntity;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;

/**
 * Dispatched whenever a storefront visitor's cookie consent decision was logged.
 *
 * Intentionally separate from the admin consent events
 * (ConsentAcceptedEvent / ConsentRevokedEvent): it is anonymous, high-volume
 * and does not write to the `consent_log` table.
 *
 * Hookable so apps can subscribe via webhook. The payload mirrors a
 * `cookie_consent_log` row and contains no visitor identifiers, so receiving it
 * is gated by the same privilege as reading the log entity.
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
readonly class CookieConsentLoggedEvent implements Hookable
{
    final public const EVENT_NAME = 'cookie.consent.logged';

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

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'consentAction' => $this->consentAction,
            'groupDecisions' => $this->groupDecisions,
            'acceptedCookies' => $this->acceptedCookies,
            'serverConfigHash' => $this->serverConfigHash,
            'renderedConfigHash' => $this->renderedConfigHash,
            'salesChannelId' => $this->salesChannelId,
            'languageId' => $this->languageId,
        ];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $permissions->isAllowed(CookieConsentLogDefinition::ENTITY_NAME, AclRoleDefinition::PRIVILEGE_READ);
    }
}
