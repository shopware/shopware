<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Media\Cms\CmsElementMediaProviderInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * Asks the media providers of a CMS element for the media the element shows
 *
 * @internal
 */
#[Package('discovery')]
class CmsSlotMediaIdResolver
{
    /**
     * @var array<string, list<CmsElementMediaProviderInterface>>
     */
    private array $providers = [];

    /**
     * @param iterable<CmsElementMediaProviderInterface> $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->getType()][] = $provider;
        }
    }

    /**
     * @param array<mixed> $config
     *
     * @return list<string>
     */
    public function getMediaIds(string $type, array $config): array
    {
        $providers = $this->providers[$type] ?? [];
        if ($providers === []) {
            return [];
        }

        $slot = new CmsSlotEntity();
        $slot->setType($type);
        $slot->setConfig($config);
        $slot->addTranslated('config', $config);

        $mediaIds = [];
        foreach ($providers as $provider) {
            array_push($mediaIds, ...$provider->getMediaIds($slot));
        }

        return $mediaIds;
    }
}
