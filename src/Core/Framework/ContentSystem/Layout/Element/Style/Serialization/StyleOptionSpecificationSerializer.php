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
        // Non-array enum/range/adminUI are coerced to null (like the is_string/is_int guards on type/maxLength)
        // so a malformed app declaration surfaces as a clean validation error, not a TypeError on the DTO.
        return new StyleOptionSpecificationDto(
            type: \is_string($data['type'] ?? null) ? $data['type'] : '',
            enum: \is_array($data['enum'] ?? null) ? $data['enum'] : null,
            range: \is_array($data['range'] ?? null) ? $data['range'] : null,
            maxLength: \is_int($data['maxLength'] ?? null) ? $data['maxLength'] : null,
            default: $data['default'] ?? null,
            adminUI: \is_array($data['adminUI'] ?? null) ? $data['adminUI'] : null,
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

        if ($dto->adminUI !== null) {
            $result['adminUI'] = $dto->adminUI;
        }

        return $result;
    }
}
