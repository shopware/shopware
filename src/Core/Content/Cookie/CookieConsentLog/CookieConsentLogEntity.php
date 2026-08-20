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

    /**
     * Every cookie of the group the visitor could choose from was accepted.
     */
    final public const DECISION_ACCEPTED = 'accepted';

    /**
     * Some, but not all cookies of the group were accepted. The exact selection
     * is in `acceptedCookies`.
     */
    final public const DECISION_PARTIAL = 'partial';

    /**
     * No cookie of the group was accepted.
     */
    final public const DECISION_REJECTED = 'rejected';

    protected string $salesChannelId;

    protected string $languageId;

    protected string $consentAction;

    /**
     * Verdict per cookie group, keyed by the group's technical name.
     *
     * @var array<string, self::DECISION_*>
     */
    protected array $groupDecisions = [];

    /**
     * Names of the accepted cookies that required consent. Technically required
     * cookies are not listed, they are not consented to.
     *
     * @var list<string>
     */
    protected array $acceptedCookies = [];

    protected string $serverConfigHash;

    protected ?string $renderedConfigHash = null;

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
     * @return array<string, self::DECISION_*>
     */
    public function getGroupDecisions(): array
    {
        return $this->groupDecisions;
    }

    /**
     * @param array<string, self::DECISION_*> $groupDecisions
     */
    public function setGroupDecisions(array $groupDecisions): void
    {
        $this->groupDecisions = $groupDecisions;
    }

    /**
     * @return list<string>
     */
    public function getAcceptedCookies(): array
    {
        return $this->acceptedCookies;
    }

    /**
     * @param list<string> $acceptedCookies
     */
    public function setAcceptedCookies(array $acceptedCookies): void
    {
        $this->acceptedCookies = $acceptedCookies;
    }

    public function getServerConfigHash(): string
    {
        return $this->serverConfigHash;
    }

    public function setServerConfigHash(string $serverConfigHash): void
    {
        $this->serverConfigHash = $serverConfigHash;
    }

    public function getRenderedConfigHash(): ?string
    {
        return $this->renderedConfigHash;
    }

    public function setRenderedConfigHash(?string $renderedConfigHash): void
    {
        $this->renderedConfigHash = $renderedConfigHash;
    }
}
