<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlTemplate;

class TemplateGroup
{
    /**
     * @var string
     */
    private $languageId;

    /**
     * @var string
     */
    private $template;

    private $salesChannelIds;

    public function __construct(string $languageId, string $template, array $salesChannelIds)
    {
        $this->languageId = $languageId;
        $this->template = $template;
        $this->salesChannelIds = $salesChannelIds;
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
}
