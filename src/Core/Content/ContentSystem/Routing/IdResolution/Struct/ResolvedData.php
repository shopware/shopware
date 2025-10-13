<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution\Struct;

use Shopware\Core\Content\ContentSystem\Routing\IdResolution\EntityIdMap;
use Shopware\Core\Content\ContentSystem\Routing\IdResolution\ParameterMap;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class ResolvedData
{
    public function __construct(
        public EntityIdMap $entityIds,
        public ParameterMap $parameters,
    ) {
    }

    public function getEntityId(string $placeholder): ?string
    {
        return $this->entityIds->get($placeholder);
    }

    public function getEntityIds(): EntityIdMap
    {
        return $this->entityIds;
    }

    /**
     * @return array<string, string|int|bool|float>
     */
    public function getValues(): array
    {
        return \array_merge($this->entityIds->toArray(), $this->parameters->toArray());
    }

    public function getValue(string $name): string|int|bool|float|null
    {
        return $this->entityIds->get($name) ?? $this->parameters->get($name);
    }
}
