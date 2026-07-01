<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Serialization;

use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\Log\Package;

/**
 * Converts the declarative binding specification representation (YAML file body, or the JSON schema
 * column of a persisted app binding) to and from the validation DTO. The id is not part of this shape:
 * it comes from the YAML body's "id" key and is supplied to the DTO separately by the loader.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class BindingSpecificationSerializer
{
    /**
     * @param array<string, mixed> $data
     */
    public function denormalize(array $data): BindingSpecificationDto
    {
        // Every facet is carried raw (the DTO types them as mixed) so a wrong-typed declaration is rejected
        // with a clean WellFormedBindingSpecification violation rather than silently coerced before
        // validation can see it.
        return new BindingSpecificationDto(
            type: $data['type'] ?? null,
            label: $data['label'] ?? null,
            resolves: $data['resolves'] ?? null,
            inputs: $data['inputs'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(BindingSpecificationDto $dto): array
    {
        return [
            'type' => $dto->type,
            'label' => $dto->label,
            'resolves' => $dto->resolves,
            'inputs' => $dto->inputs,
        ];
    }
}
