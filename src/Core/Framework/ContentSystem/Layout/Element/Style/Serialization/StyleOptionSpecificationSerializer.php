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
        // type, default and adminUI are coerced to a safe value of their declared PHP type, so a wrong-typed
        // value in a raw app declaration cannot raise a TypeError on the typed DTO constructor. enum, range and
        // maxLength are carried raw (the DTO types them as mixed) so a wrong-typed facet — a non-array enum or
        // range, a non-integer maxLength — is rejected with a clean TypedStyleOption violation rather than
        // silently dropped before validation can see it.
        return new StyleOptionSpecificationDto(
            type: \is_string($data['type'] ?? null) ? $data['type'] : '',
            enum: $data['enum'] ?? null,
            range: $data['range'] ?? null,
            maxLength: $data['maxLength'] ?? null,
            default: \is_scalar($data['default'] ?? null) ? $data['default'] : null,
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
