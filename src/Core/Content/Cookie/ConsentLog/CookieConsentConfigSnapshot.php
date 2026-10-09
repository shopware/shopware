<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ConsentLog;

use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * The cookie banner configuration as it was presented to visitors, stored once per
 * configuration hash. A consent record references it through its `configHash`, so
 * it can be shown later what the visitor agreed to.
 */
#[Package('framework')]
final readonly class CookieConsentConfigSnapshot implements \JsonSerializable
{
    /**
     * @param CookieGroupCollection $cookieGroups the cookie groups as the banner received them
     */
    public function __construct(
        public string $configHash,
        public CookieGroupCollection $cookieGroups,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @return array{configHash: string, cookieGroups: CookieGroupCollection, createdAt: string}
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
