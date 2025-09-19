<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('discovery')]
class MediaPathChangedEvent extends Event
{
    /**
     * @var array<array{mediaId: string, thumbnailId: ?string, path: string, mimeType: ?string}>
     */
    public array $changed = [];

    public function __construct(public Context $context)
    {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed - use mediaWithMimeType instead
     */
    public function media(string $mediaId, string $path): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            'The method MediaPathChangedEvent::media is deprecated and will be removed in v6.8.0. Use mediaWithMimeType instead.'
        );
        $this->mediaWithMimeType($mediaId, $path);
    }

    public function mediaWithMimeType(string $mediaId, string $path, ?string $mimeType = null): void
    {
        $this->changed[] = [
            'mediaId' => $mediaId,
            'thumbnailId' => null,
            'path' => $path,
            'mimeType' => $mimeType,
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed - use thumbnailWithMimeType instead
     */
    public function thumbnail(string $mediaId, string $thumbnailId, string $path): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            'The method MediaPathChangedEvent::thumbnail is deprecated and will be removed in v6.8.0. Use thumbnailWithMimeType instead.'
        );
        $this->thumbnailWithMimeType($mediaId, $thumbnailId, $path);
    }

    public function thumbnailWithMimeType(
        string $mediaId,
        string $thumbnailId,
        string $path,
        ?string $mimeType = null
    ): void {
        $this->changed[] = [
            'mediaId' => $mediaId,
            'thumbnailId' => $thumbnailId,
            'path' => $path,
            'mimeType' => $mimeType,
        ];
    }
}
