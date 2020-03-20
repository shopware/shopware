<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer\Indexing;

use Shopware\Core\Content\Media\Event\MediaThumbnailDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.3.0 - Use \Shopware\Core\Content\Media\DataAbstractionLayer\MediaIndexer instead
 */
class MediaThumbnailIndexer implements IndexerInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [];
    }

    public function index(\DateTimeInterface $timestamp): void
    {
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        return null;
    }

    public function onDelete(MediaThumbnailDeletedEvent $event): void
    {
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
    }

    public static function getName(): string
    {
        return 'Swag.MediaThumbnailIndexer';
    }
}
