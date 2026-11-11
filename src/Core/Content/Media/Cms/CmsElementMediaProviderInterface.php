<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * Tells which media a CMS element shows, so replacing the file of a media invalidates the cached pages showing it.
 * Services implementing it are tagged with `shopware.cms.element_media_provider` automatically.
 * Several providers can be registered for the same element type, their media are combined.
 * Elements whose media can be found by a path in their config can use the `CmsElementMediaFieldProvider` instead.
 */
#[Package('discovery')]
interface CmsElementMediaProviderInterface
{
    /**
     * The type of the CMS element, like `image`
     */
    public function getType(): string;

    /**
     * Returns the ids of the media the element shows from its static config.
     * Mapped values like `category.media` are found through the entity they are mapped from.
     *
     * @return list<string>
     */
    public function getMediaIds(CmsSlotEntity $slot): array;
}
