<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlTemplate;

use Shopware\Core\Framework\Log\Package;

#[Package('sales-channel')]
class TemplateGroup
{
    /**
     * @param array<string> $salesChannelIds
     */
    public function __construct(
        private readonly string $languageId,
        private readonly string $template,
        private readonly array $salesChannelIds,
        private array $salesChannels = []
    ) {
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getSalesChannelIds(): array
    {
        return $this->salesChannelIds;
    }

    public function getSalesChannels(): array
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(array $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }
}
