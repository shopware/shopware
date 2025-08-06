<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Framework\Context;
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

    public function media(string $mediaId, string $path, ?string $mimeType = null): void
    {
        $this->changed[] = [
            'mediaId' => $mediaId,
            'thumbnailId' => null,
            'path' => $path,
            'mimeType' => $mimeType,
        ];
    }

    public function thumbnail(string $mediaId, string $thumbnailId, string $path, ?string $mimeType = null): void
    {
        $this->changed[] = [
            'mediaId' => $mediaId,
            'thumbnailId' => $thumbnailId,
            'path' => $path,
            'mimeType' => $mimeType,
        ];
    }
}
