<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @phpstan-type EntityLoaderConfigData array{
 *   entity: non-empty-string,
 *   property: non-empty-string,
 *   associations?: list<non-empty-string>
 * }
 *
 * @internal
 */
#[Package('discovery')]
final class EntityLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string $entity
     * @param non-empty-string $property
     * @param list<non-empty-string> $associations
     */
    public function __construct(
        public readonly string $entity,
        public readonly string $property,
        public readonly array $associations
    ) {
    }

    public function getDecorated(): AbstractContentDataLoaderConfig
    {
        throw new DecorationPatternException(self::class);
    }
}
