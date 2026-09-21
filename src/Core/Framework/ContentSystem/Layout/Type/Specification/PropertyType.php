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

    /**
     * @return array<string, PropertySpecification>|null
     */
    public function properties(): ?array
    {
        return $this->properties;
    }

    public function isPrimitive(): bool
    {
        return \in_array($this->type, self::PRIMITIVE_TYPES, true);
    }

    /**
     * The primitive types a value must satisfy at least one of, or `null` when the declaration constrains
     * nothing: a bare `object` or an FQCN admits whatever the client authored, and so does a union carrying
     * either, because that member alone accepts every value.
     *
     * A union's declared type is an array, for which {@see isPrimitive()} always answers false, so the members
     * are tested against {@see PRIMITIVE_TYPES} individually.
     *
     * @return list<string>|null
     */
    public function enforceableTypes(): ?array
    {
        if (\is_string($this->type)) {
            return \in_array($this->type, self::PRIMITIVE_TYPES, true) ? [$this->type] : null;
        }

        if ($this->type === []) {
            return null;
        }

        foreach ($this->type as $member) {
            if (!\in_array($member, self::PRIMITIVE_TYPES, true)) {
                return null;
            }
        }

        return $this->type;
    }

    /**
     * Whether a raw value conforms to this declaration. A null is admissible under every primitive, because
     * whether a key may be null is the required-input rule's business, not this one's.
     */
    public function admits(mixed $value): bool
    {
        $types = $this->enforceableTypes();

        if ($types === null || $value === null) {
            return true;
        }

        foreach ($types as $type) {
            if (self::matchesPrimitive($value, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * `number` admits an integer as well as a float — JSON carries no distinction a client can be held to —
     * while `integer` admits only an integer.
     */
    private static function matchesPrimitive(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => \is_string($value),
            'integer' => \is_int($value),
            'number' => \is_int($value) || \is_float($value),
            'boolean' => \is_bool($value),
            default => false,
        };
    }
}
