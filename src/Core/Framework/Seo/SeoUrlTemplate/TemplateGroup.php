<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Seo\SeoUrlTemplate;

use Shopware\Core\System\SalesChannel\SalesChannelEntity;

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

    /**
     * @var array
     */
    private $salesChannelIds;

    /**
     * @var array
     */
    private $salesChannels;

    public function __construct(string $languageId, string $template, array $salesChannels)
    {
        $this->languageId = $languageId;
        $this->template = $template;
        $this->salesChannels = $salesChannels;
        $this->salesChannelIds = array_map(function (?SalesChannelEntity $value) {
            if ($value === null) {
                return null;
            }

            return $value->getId();
        }, $salesChannels);
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
}
