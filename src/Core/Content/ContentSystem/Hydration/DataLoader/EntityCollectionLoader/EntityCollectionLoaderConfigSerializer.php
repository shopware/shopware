<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityCollectionLoader;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ContentDataLoaderConfigInterface;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ContentDataLoaderConfigSerializerInterface;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * Serializer for entity_collection source delegates to EntityLoaderConfigSerializer
 * as both sources use identical config structure (EntityLoaderConfig).
 *
 * @internal
 */
#[Package('discovery')]
class EntityCollectionLoaderConfigSerializer implements ContentDataLoaderConfigSerializerInterface
{
    public function __construct(
        private readonly EntityLoaderConfigSerializer $delegate
    ) {
    }

    public static function getSource(): string
    {
        return 'entity_collection';
    }

    public function decode(array $data): ContentDataLoaderConfigInterface
    {
        return $this->delegate->decode($data);
    }

    public function encode(ContentDataLoaderConfigInterface $config): array
    {
        return $this->delegate->encode($config);
    }
}
