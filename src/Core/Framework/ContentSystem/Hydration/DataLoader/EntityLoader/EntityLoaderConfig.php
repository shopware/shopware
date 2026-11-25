<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
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
#[Package('framework')]
final readonly class EntityLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
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

    /**
     * @return EntityLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        $data = [
            'entity' => $this->entity,
            'property' => $this->property,
        ];

        if ($this->associations !== []) {
            $data['associations'] = $this->associations;
        }

        return $data;
    }
}
