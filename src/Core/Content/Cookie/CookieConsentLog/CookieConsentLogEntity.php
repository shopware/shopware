<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\CookieConsentLog;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
class CookieConsentLogEntity extends Entity
{
    use EntityIdTrait;

    protected string $salesChannelId;

    protected string $languageId;

    protected string $consentAction;

    /**
     * @var list<string>
     */
    protected array $acceptedGroups = [];

    protected string $configHash;

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

    public function getConsentAction(): string
    {
        return $this->consentAction;
    }

    public function setConsentAction(string $consentAction): void
    {
        $this->consentAction = $consentAction;
    }

    /**
     * @return list<string>
     */
    public function getAcceptedGroups(): array
    {
        return $this->acceptedGroups;
    }

    /**
     * @param list<string> $acceptedGroups
     */
    public function setAcceptedGroups(array $acceptedGroups): void
    {
        $this->acceptedGroups = $acceptedGroups;
    }

    public function getConfigHash(): string
    {
        return $this->configHash;
    }

    public function setConfigHash(string $configHash): void
    {
        $this->configHash = $configHash;
    }
}
