<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\TypeDetector\TypeDetector;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class MetadataUpdater
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var TypeDetector
     */
    private $typeDetector;

    /**
     * @var MetadataLoader
     */
    private $metadataLoader;

    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    public function __construct(Connection $connection, TypeDetector $typeDetector, MetadataLoader $metadataLoader, EntityCacheKeyGenerator $cacheKeyGenerator, TagAwareAdapterInterface $cache)
    {
        $this->connection = $connection;
        $this->typeDetector = $typeDetector;
        $this->metadataLoader = $metadataLoader;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
    }

    public function updateMetadataAndMediaType(MediaFile $mediaFile, string $mediaId): void
    {
        $mediaType = $this->typeDetector->detect($mediaFile);
        $rawMetadata = $this->metadataLoader->loadFromFile($mediaFile, $mediaType);

        $data = [
            'meta_data' => serialize($rawMetadata),
            'media_type' => serialize($mediaType),
        ];

        $this->connection->update(MediaDefinition::getEntityName(), $data, ['id' => Uuid::fromHexToBytes($mediaId)]);

        $cacheKeys = $this->cacheKeyGenerator->getEntityTag($mediaId, MediaDefinition::class);
        $this->cache->invalidateTags([$cacheKeys]);
    }

    public function updateMediaType(MediaFile $mediaFile, string $mediaId): void
    {
        $mediaType = $this->typeDetector->detect($mediaFile);

        $data = [
            'media_type' => serialize($mediaType),
        ];

        $this->connection->update(MediaDefinition::getEntityName(), $data, ['id' => Uuid::fromHexToBytes($mediaId)]);

        $cacheKeys = $this->cacheKeyGenerator->getEntityTag($mediaId, MediaDefinition::class);
        $this->cache->invalidateTags([$cacheKeys]);
    }
}
