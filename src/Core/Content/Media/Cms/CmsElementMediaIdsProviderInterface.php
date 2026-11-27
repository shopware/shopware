<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Cms;

use Shopware\Core\Framework\Log\Package;

/**
 * Tells which media a CMS element shows, so replacing the file of a media invalidates the cached pages showing it.
 * Services implementing it are tagged with `shopware.cms.element_media_ids_provider` automatically.
 * Elements whose media ids can be found by paths in their config can register a `CmsElementMediaIdsProvider` instead.
 */
#[Package('discovery')]
interface CmsElementMediaIdsProviderInterface
{
    /**
     * Whether the provider knows the media of the CMS element type, like `image`
     */
    public function supports(string $type): bool;

    /**
     * Returns the ids of the media the element shows from its static config.
     * Mapped values, like `product.manufacturer.media`, are not part of the config and are not returned.
     *
     * @param array<string, array{source?: string, value?: mixed}> $config raw config of the element, its fields by name, like `['media' => ['source' => 'static', 'value' => '<media id>']]`, it is not validated
     *
     * @return list<string>
     */
    public function getMediaIds(array $config): array;
}
