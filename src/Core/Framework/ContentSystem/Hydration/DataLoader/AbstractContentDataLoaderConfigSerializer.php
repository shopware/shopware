<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\Log\Package;

/**
 * Abstract base for serializing data loader configurations.
 *
 * Serializers encode/decode config objects to/from arrays for storage
 * and transmission. Each loader type has a corresponding serializer.
 */
#[Package('discovery')]
abstract class AbstractContentDataLoaderConfigSerializer
{
    abstract public function getDecorated(): AbstractContentDataLoaderConfigSerializer;

    /**
     * Returns the source identifier for DI service location.
     * This method is used by the ServiceLocator for indexing.
     *
     * @return non-empty-string
     */
    abstract public static function getSource(): string;

    /**
     * @param array<string, mixed> $data
     */
    abstract public function decode(array $data): AbstractContentDataLoaderConfig;

    /**
     * @return array<string, mixed>
     */
    abstract public function encode(AbstractContentDataLoaderConfig $config): array;
}
