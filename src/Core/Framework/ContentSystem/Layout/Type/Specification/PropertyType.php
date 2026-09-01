<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Specification;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TranslatableTypeValidator;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TypedEnumValidator;
use Shopware\Core\Framework\Log\Package;

/**
 * $type accepts primitives (`string`, `integer`, `boolean`, `number`), `object`,
 * class-string<Struct> FQCNs, and lists for union-like declarations.
 * `enum` and `translatable` are ignored for non-primitive types. {@see TypedEnumValidator} {@see TranslatableTypeValidator}
 *
 * @phpstan-type PropertyTypeSchema = array{
 *     type: string|list<string>,
 *     translatable: bool,
 *     enum: list<string|int|float|bool>|null,
 *     default: string|int|float|bool|null,
 *     properties: array<string, array<string, mixed>>|null
 * }
 */
#[Package('framework')]
final readonly class PropertyType
{
    /**
     * The canonical primitive type set: any other `type` value is a `class-string<Struct>` FQCN.
     */
    public const PRIMITIVE_TYPES = ['string', 'integer', 'number', 'boolean'];

    /**
     * @param string|list<string> $type
     * @param list<string|int|float|bool>|null $enum
     * @param array<string, PropertySpecification>|null $properties
     */
    public function __construct(
        private string|array $type,
        private bool $translatable,
        private ?array $enum,
        private string|int|float|bool|null $default,
        private ?array $properties = null,
    ) {
    }

    /**
     * @return PropertyTypeSchema
     */
    public function toSchema(): array
    {
        $properties = null;

        if ($this->properties !== null) {
            $properties = array_map(
                static fn (PropertySpecification $property): array => $property->toSchema(),
                $this->properties
            );
        }

        return [
            'type' => $this->type,
            'translatable' => $this->translatable,
            'enum' => $this->enum,
            'default' => $this->default,
            'properties' => $properties,
        ];
    }

    /**
     * @return string|list<string>
     */
    public function type(): string|array
    {
        return $this->type;
    }

    public function default(): string|int|float|bool|null
    {
        return $this->default;
    }

    public function isPrimitive(): bool
    {
        return \in_array($this->type, self::PRIMITIVE_TYPES, true);
    }
}
