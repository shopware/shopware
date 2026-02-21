<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

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
final readonly class EntityLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @codeCoverageIgnore
     *
     * @param non-empty-string $entity
     * @param non-empty-string $property
     * @param list<non-empty-string> $associations
     */
    public function __construct(
        public string $entity,
        public string $property,
        public array $associations
    ) {
    }
}
