<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Specification;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TranslatableTypeValidator;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TypedEnumValidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * $type accepts primitives (`string`, `integer`, `boolean`, `number`), `object`,
 * class-string<Struct> FQCNs, and lists for union-like declarations.
 * `enum` and `translatable` are ignored for non-primitive types. {@see TypedEnumValidator} {@see TranslatableTypeValidator}
 *
 * @phpstan-type PropertyTypeSchema = array{
 *     type: string|list<string>,
 *     contextTypes: list<string>,
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
            'contextTypes' => $this->contextTypes(),
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
     * Which kinds of mapping candidate can fill this property: a single value, a collection, or — for a
     * union or a bare `object` — either.
     *
     * This exists for the Administration's selection UI, which filters the catalogue down to the candidates
     * that fit a property and cannot do the job with `type` alone: it has no way to tell `MediaCollection`
     * from `MediaEntity`, since resolving a PHP class hierarchy in the browser is not on offer. Without this
     * the gallery's `MediaCollection` property offers a category's single image, and an author who picks it
     * gets an element that renders nothing.
     *
     * A collection-ness answer is the most the Administration needs — the finer assignability question is
     * still `Mapping/MappingTypeCompatibility::permits()`'s at the write boundary, and still authoritative.
     *
     * @return list<string> {@see ContextType} values, in declaration order and without repeats
     */
    public function contextTypes(): array
    {
        $accepted = [];

        foreach (\is_array($this->type) ? $this->type : [$this->type] as $member) {
            foreach ($this->contextTypesFor($member) as $contextType) {
                if (!\in_array($contextType, $accepted, true)) {
                    $accepted[] = $contextType;
                }
            }
        }

        return $accepted;
    }

    /**
     * @return list<string>
     */
    private function contextTypesFor(string $type): array
    {
        // Bare `object` names an object without naming which, so it rules nothing out.
        if ($type === 'object') {
            return [ContextType::Single->value, ContextType::Collection->value];
        }

        if (\in_array($type, self::PRIMITIVE_TYPES, true)) {
            return [ContextType::Single->value];
        }

        return is_a($type, Collection::class, true)
            ? [ContextType::Collection->value]
            : [ContextType::Single->value];
    }
}
