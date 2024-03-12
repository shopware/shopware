<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('buyers-experience')]
class ThumbnailsGeneratedEvent extends Event
{
    /**
     * @var array<array{mediaId: string, thumbnailId: string, path: string}>
     */
    public array $generated = [];

    public function __construct(public Context $context)
    {
    }

    public function add(string $mediaId, string $thumbnailId, string $path): void
    {
        $this->generated[] = [
            'mediaId' => $mediaId,
            'thumbnailId' => $thumbnailId,
            'path' => $path,
        ];
    }
}
