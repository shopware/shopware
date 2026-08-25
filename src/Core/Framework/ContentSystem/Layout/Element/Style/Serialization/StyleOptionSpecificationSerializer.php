<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Serialization;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto\StyleOptionSpecificationDto;
use Shopware\Core\Framework\Log\Package;

/**
 * Converts the declarative style option representation (YAML file body, or the JSON schema column of
 * a persisted app option) to and from the validation DTO. The option name is not part of this shape:
 * it comes from the file or the DB row and is supplied to the DTO separately.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class StyleOptionSpecificationSerializer
{
    /**
     * @param array<string, mixed> $data
     */
    public function denormalize(array $data): StyleOptionSpecificationDto
    {
        // Only type is coerced (to a safe string so the typed DTO constructor cannot raise a TypeError). Every
        // other facet is carried raw (the DTO types them as mixed) so a wrong-typed declaration — a non-array
        // enum/range/adminUI, a non-integer maxLength, a non-scalar default — is rejected with a clean
        // TypedStyleOption violation rather than silently coerced before validation can see it.
        return new StyleOptionSpecificationDto(
            type: \is_string($data['type'] ?? null) ? $data['type'] : '',
            enum: $data['enum'] ?? null,
            range: $data['range'] ?? null,
            maxLength: $data['maxLength'] ?? null,
            default: $data['default'] ?? null,
            breakpointAware: $data['breakpointAware'] ?? null,
            adminUI: $data['adminUI'] ?? null,
            kind: $data['kind'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(StyleOptionSpecificationDto $dto): array
    {
        $result = ['type' => $dto->type];

        if ($dto->enum !== null) {
            $result['enum'] = $dto->enum;
        }

        if ($dto->range !== null) {
            $result['range'] = $dto->range;
        }

        if ($dto->maxLength !== null) {
            $result['maxLength'] = $dto->maxLength;
        }

        if ($dto->default !== null) {
            $result['default'] = $dto->default;
        }

        if ($dto->breakpointAware !== null) {
            // false is emitted (false !== null), so an opt-out option is not restored to the default on round-trip.
            $result['breakpointAware'] = $dto->breakpointAware;
        }

        if ($dto->adminUI !== null) {
            $result['adminUI'] = $dto->adminUI;
        }

        if ($dto->kind !== null) {
            $result['kind'] = $dto->kind;
        }

        return $result;
    }
}
