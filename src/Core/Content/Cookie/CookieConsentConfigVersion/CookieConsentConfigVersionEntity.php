<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\CookieConsentConfigVersion;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
class CookieConsentConfigVersionEntity extends Entity
{
    use EntityIdTrait;

    protected string $configHash;

    protected string $salesChannelId;

    protected string $languageId;

    /**
     * @var array<int|string, mixed>
     */
    protected array $cookieGroups = [];

    public function getConfigHash(): string
    {
        return $this->configHash;
    }

    public function setConfigHash(string $configHash): void
    {
        $this->configHash = $configHash;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getCookieGroups(): array
    {
        return $this->cookieGroups;
    }

    /**
     * @param array<int|string, mixed> $cookieGroups
     */
    public function setCookieGroups(array $cookieGroups): void
    {
        $this->cookieGroups = $cookieGroups;
    }
}
