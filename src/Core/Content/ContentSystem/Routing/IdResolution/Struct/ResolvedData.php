<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution\Struct;

use Shopware\Core\Content\ContentSystem\Routing\IdResolution\EntityIdMap;
use Shopware\Core\Content\ContentSystem\Routing\IdResolution\ParameterMap;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('discovery')]
class ResolvedData extends Struct
{
    public function __construct(
        protected readonly EntityIdMap $entityIds,
        protected readonly ParameterMap $parameters,
        protected ?string $resolvedLayoutId = null
    ) {
    }

    public static function empty(): self
    {
        return new self(
            EntityIdMap::empty(),
            ParameterMap::empty()
        );
    }

    public function getEntityId(string $placeholder): ?string
    {
        return $this->entityIds->get($placeholder);
    }

    public function getParameter(string $name): int|string|bool|float|null
    {
        return $this->parameters->get($name);
    }

    public function hasEntityId(string $placeholder): bool
    {
        return $this->entityIds->has($placeholder);
    }

    public function hasParameter(string $name): bool
    {
        return $this->parameters->has($name);
    }

    public function getEntityIds(): EntityIdMap
    {
        return $this->entityIds;
    }

    public function getParameters(): ParameterMap
    {
        return $this->parameters;
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

    public function getResolvedLayoutId(): ?string
    {
        return $this->resolvedLayoutId;
    }

    public function setResolvedLayoutId(?string $layoutId): void
    {
        $this->resolvedLayoutId = $layoutId;
    }

    public function resolvePlaceholdersInString(string $input): string
    {
        $values = $this->getValues();

        foreach ($values as $key => $value) {
            if (\is_scalar($value)) {
                $placeholder = '{{' . $key . '}}';
                $input = \str_replace($placeholder, (string) $value, $input);
            }
        }

        return $input;
    }

    public function withEntityId(string $placeholder, string $entityId): self
    {
        return new self(
            $this->entityIds->add($placeholder, $entityId),
            $this->parameters,
            $this->resolvedLayoutId
        );
    }

    public function withParameter(string $name, int|string|bool|float $value): self
    {
        return new self(
            $this->entityIds,
            $this->parameters->add($name, $value),
            $this->resolvedLayoutId
        );
    }

    public function mergeEntityIds(EntityIdMap $entityIds): self
    {
        return new self(
            $this->entityIds->merge($entityIds),
            $this->parameters,
            $this->resolvedLayoutId
        );
    }

    public function mergeParameters(ParameterMap $parameters): self
    {
        return new self(
            $this->entityIds,
            $this->parameters->merge($parameters),
            $this->resolvedLayoutId
        );
    }

    public function getApiAlias(): string
    {
        return 'content_resolved_data';
    }
}
