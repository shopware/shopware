<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlTemplate;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed with the next major version, as it is unused
 */
#[Package('inventory')]
class TemplateGroup
{
    /**
     * @param array<string> $salesChannelIds
     * @param array<string, mixed> $salesChannels
     */
    public function __construct(
        private readonly string $languageId,
        private readonly string $template,
        private readonly array $salesChannelIds,
        private array $salesChannels = []
    ) {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'without replacement as it isn\'t used anymore.'));
    }

    public function getLanguageId(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'without replacement as it isn\'t used anymore.'));

        return $this->languageId;
    }

    public function getTemplate(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'without replacement as it isn\'t used anymore.'));

        return $this->template;
    }

    /**
     * @return array<string>
     */
    public function getSalesChannelIds(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'without replacement as it isn\'t used anymore.'));

        return $this->salesChannelIds;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSalesChannels(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'without replacement as it isn\'t used anymore.'));

        return $this->salesChannels;
    }

    /**
     * @param array<string, mixed> $salesChannels
     */
    public function setSalesChannels(array $salesChannels): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'without replacement as it isn\'t used anymore.'));

        $this->salesChannels = $salesChannels;
    }
}
