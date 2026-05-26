<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 *
 * @internal
 */
#[Package('framework')]
class AgenticDiscoverySalesChannelConfigEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $salesChannelId;

    protected bool $active = true;

    protected bool $exposeAgentsMd = true;

    protected bool $exposeLlmsTxt = true;

    protected bool $exposeLlmsFullTxt = true;

    protected bool $exposeAgenticSitemap = true;

    protected ?string $customIntro = null;

    /**
     * Additional merchant-defined hard rules that should be rendered under the
     * "Rules" section of /agents.md. Each entry is a single-line directive
     * (e.g. "Do not recommend out-of-stock items unless explicitly asked.").
     *
     * @var list<string>|null
     */
    protected ?array $customAgentRules = null;

    /**
     * Free-form additional sections rendered into /agents.md after the
     * default sections. Each entry has shape `{title: string, body: string}`
     * where `body` is plain markdown.
     *
     * @var list<array{title: string, body: string}>|null
     */
    protected ?array $customSections = null;

    protected ?SalesChannelEntity $salesChannel = null;

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function isExposeAgentsMd(): bool
    {
        return $this->exposeAgentsMd;
    }

    public function setExposeAgentsMd(bool $exposeAgentsMd): void
    {
        $this->exposeAgentsMd = $exposeAgentsMd;
    }

    public function isExposeLlmsTxt(): bool
    {
        return $this->exposeLlmsTxt;
    }

    public function setExposeLlmsTxt(bool $exposeLlmsTxt): void
    {
        $this->exposeLlmsTxt = $exposeLlmsTxt;
    }

    public function isExposeLlmsFullTxt(): bool
    {
        return $this->exposeLlmsFullTxt;
    }

    public function setExposeLlmsFullTxt(bool $exposeLlmsFullTxt): void
    {
        $this->exposeLlmsFullTxt = $exposeLlmsFullTxt;
    }

    public function isExposeAgenticSitemap(): bool
    {
        return $this->exposeAgenticSitemap;
    }

    public function setExposeAgenticSitemap(bool $exposeAgenticSitemap): void
    {
        $this->exposeAgenticSitemap = $exposeAgenticSitemap;
    }

    public function getCustomIntro(): ?string
    {
        return $this->customIntro;
    }

    public function setCustomIntro(?string $customIntro): void
    {
        $this->customIntro = $customIntro;
    }

    /**
     * @return list<string>|null
     */
    public function getCustomAgentRules(): ?array
    {
        return $this->customAgentRules;
    }

    /**
     * @param list<string>|null $customAgentRules
     */
    public function setCustomAgentRules(?array $customAgentRules): void
    {
        $this->customAgentRules = $customAgentRules;
    }

    /**
     * @return list<array{title: string, body: string}>|null
     */
    public function getCustomSections(): ?array
    {
        return $this->customSections;
    }

    /**
     * @param list<array{title: string, body: string}>|null $customSections
     */
    public function setCustomSections(?array $customSections): void
    {
        $this->customSections = $customSections;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}
