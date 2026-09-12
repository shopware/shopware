<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ConsentLog;

use Shopware\Core\Framework\Log\Package;

/**
 * The cookie banner configuration as it was presented to visitors, stored once per
 * configuration hash. A consent record references it through its `configHash`, so
 * it can be shown later what the visitor agreed to.
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
final readonly class CookieConsentConfigSnapshot implements \JsonSerializable
{
    /**
     * @param list<mixed> $cookieGroups JSON-serializable cookie groups, as the banner received them
     */
    public function __construct(
        public string $configHash,
        public array $cookieGroups,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'configHash' => $this->configHash,
            'cookieGroups' => $this->cookieGroups,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::RFC3339_EXTENDED),
        ];
    }
}
