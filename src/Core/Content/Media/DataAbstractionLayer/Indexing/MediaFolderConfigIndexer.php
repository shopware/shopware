<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;

/**
 * @deprecated tag:v6.3.0 - Use \Shopware\Core\Content\Media\DataAbstractionLayer\MediaFolderConfigurationIndexer instead
 */
class MediaFolderConfigIndexer implements IndexerInterface
{
    public function index(\DateTimeInterface $timestamp): void
    {
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        return null;
    }

    public static function getName(): string
    {
        return 'Swag.MediaFolderConfigIndexer';
    }
}
