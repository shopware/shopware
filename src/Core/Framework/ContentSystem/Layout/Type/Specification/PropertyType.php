<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * $type accepts primitives (`string`, `integer`, `boolean`, `number`) and class-string<Struct> FQCNs.
 * `enum` and `translatable` are ignored for non-primitive types. {@see ValidPropertyConstraintsValidator}
 *
 * @phpstan-type PropertyTypeSchema = array{type: string, translatable: bool, enum: list<string|int|float|bool>|null, default: string|int|float|bool|null}
 */
#[Package('framework')]
final readonly class PropertyType
{
    /**
     * @param list<string|int|float|bool>|null $enum
     */
    public function __construct(
        private string $type,
        private bool $translatable,
        private ?array $enum,
        private string|int|float|bool|null $default,
    ) {
    }

    /**
     * @return PropertyTypeSchema
     */
    public function toSchema(): array
    {
        return [
            'type' => $this->type,
            'translatable' => $this->translatable,
            'enum' => $this->enum,
            'default' => $this->default,
        ];
    }
}
