<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ConsentLog;

use Shopware\Core\Framework\Log\Package;

/**
 * One recorded cookie consent decision.
 *
 * The record is pseudonymous: `consentId` is an opaque token the visitor's client
 * generated and keeps in its own cookie storage. It links the decisions of one
 * visitor and lets a data subject retrieve their own records, but it is neither an
 * IP address, a session id nor a customer id.
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
final readonly class CookieConsentRecord implements \JsonSerializable
{
    /**
     * A lookup handle, not a secret. Restricted so it is safe as a CLI argument and as
     * part of a file name, and long enough for a UUID or a similar client-generated token.
     */
    public const CONSENT_ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    /**
     * @param array<string, CookieConsentDecision> $groupDecisions verdict per cookie group, keyed by technical name
     * @param list<string> $acceptedCookies names of the accepted cookies that required consent
     * @param string $configHash identifies the banner configuration snapshot the decision was made on
     */
    public function __construct(
        public string $consentId,
        public CookieConsentAction $consentAction,
        public array $groupDecisions,
        public array $acceptedCookies,
        public string $configHash,
        public string $salesChannelId,
        public string $languageId,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'consentId' => $this->consentId,
            'consentAction' => $this->consentAction->value,
            'groupDecisions' => array_map(static fn (CookieConsentDecision $decision) => $decision->value, $this->groupDecisions),
            'acceptedCookies' => $this->acceptedCookies,
            'configHash' => $this->configHash,
            'salesChannelId' => $this->salesChannelId,
            'languageId' => $this->languageId,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::RFC3339_EXTENDED),
        ];
    }
}
