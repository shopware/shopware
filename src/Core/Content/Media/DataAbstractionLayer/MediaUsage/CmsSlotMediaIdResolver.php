<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage;

use Shopware\Core\Content\Media\Cms\CmsElementMediaIdsProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * Asks the first media provider supporting a CMS element for the media the element shows
 *
 * @internal
 */
#[Package('discovery')]
class CmsSlotMediaIdResolver
{
    /**
     * @param iterable<CmsElementMediaIdsProvider> $providers
     */
    public function __construct(private readonly iterable $providers)
    {
    }

    /**
     * @param array<string, array{source?: string, value?: mixed}> $config
     *
     * @return list<string>
     */
    public function getMediaIds(string $type, array $config): array
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($type)) {
                return $provider->getMediaIds($config);
            }
        }

        return [];
    }
}
