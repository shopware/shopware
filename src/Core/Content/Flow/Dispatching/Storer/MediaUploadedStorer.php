<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\Aware\MediaUploadedAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

#[Package('content')]
class MediaUploadedStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof MediaUploadedAware || isset($stored[MediaUploadedAware::MEDIA_ID])) {
            return $stored;
        }

        $stored[MediaUploadedAware::MEDIA_ID] = $event->getMediaId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(MediaUploadedAware::MEDIA_ID)) {
            return;
        }

        $storable->setData(MediaUploadedAware::MEDIA_ID, $storable->getStore(MediaUploadedAware::MEDIA_ID));
    }
}
