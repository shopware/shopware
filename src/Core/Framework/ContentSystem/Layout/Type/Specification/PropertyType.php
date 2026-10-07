<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Specification;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TranslatableTypeValidator;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TypedEnumValidator;
use Shopware\Core\Framework\Log\Package;

/**
 * $type accepts primitives (`string`, `integer`, `boolean`, `number`), `object`,
 * class-string<Struct> FQCNs, and lists for union-like declarations.
 * `enum` is ignored for non-primitive types; `translatable` is a declaration error on any type but a lone
 * primitive. {@see TypedEnumValidator} {@see TranslatableTypeValidator}
 *
 * These members serve the stored tree rather than the published schema: {@see translatable()} reads the flag,
 * {@see inStoredShape()} is the one shape rule for a default in storage ({@see storedDefault()} applies it to the
 * declared default), {@see admits()} is the one
 * conformance predicate answering whether a stored value matches this declared type, {@see admitsMapEntry()}
 * judges one language-map entry against the declared primitive, and {@see describe()} renders the declaration
 * for the violation messages both reporters share.
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

    public function translatable(): bool
    {
        return $this->translatable;
    }

    /**
     * The declared type as a violation message names it. The translatable flag is spelled out because the same
     * lone primitive declaration admits a bare scalar without it and only a language map with it, so the flag is
     * what a client needs to read the report.
     */
    public function describe(): string
    {
        $declared = implode('|', (array) $this->type);

        if (!$this->translatable) {
            return $declared;
        }

        return $declared . ' (translatable)';
    }

    /**
     * The declared default in the shape storage holds it, by the rule {@see inStoredShape()} states. A declaration
     * with no default seeds nothing, which the null return reports.
     *
     * @return string|int|float|bool|array<string, string|int|float|bool>|null
     */
    public function storedDefault(): string|int|float|bool|array|null
    {
        return $this->inStoredShape($this->default);
    }

    /**
     * The one statement of what shape a default value takes in storage: a translatable property stores one value
     * per language, so the value seeds under the anchor language key, and every other property stores it bare.
     * Only a non-null value is wrapped, because null is no language-map entry: it is returned as is and left to
     * the caller's own validation to reject. A falsy value (`false`, `0`, `''`) is wrapped like any other.
     */
    public function inStoredShape(mixed $value): mixed
    {
        if ($value === null || !$this->translatable) {
            return $value;
        }

        return [Defaults::LANGUAGE_SYSTEM => $value];
    }

    /**
     * Whether a stored value conforms to this declared type, total over every {@see StoredValue} variant. A
     * translatable property admits only a non-empty language map whose every entry matches the declared
     * primitive ({@see admitsMapEntry()}). Every other declaration keeps the primitive match table: a union
     * carrying a non-primitive, a bare `object` and an FQCN constrain nothing, a lone or all-primitive union
     * declaration is matched against the unwrapped value, and the null variant is admitted throughout, because
     * whether a key may be absent or null is the required-input rule's business.
     */
    public function admits(StoredValue $value): bool
    {
        if ($this->translatable) {
            return $this->admitsLanguageMap($value);
        }

        if ($value->isNull()) {
            return true;
        }

        $enforceable = $this->enforceableTypes();

        if ($enforceable === null) {
            return true;
        }

        $raw = $value->jsonSerialize();

        foreach ($enforceable as $primitive) {
            if ($this->matchesPrimitive($raw, $primitive)) {
                return true;
            }
        }

        return false;
    }

    public function isPrimitive(): bool
    {
        return \in_array($this->type, self::PRIMITIVE_TYPES, true);
    }

    /**
     * Whether one language-map entry matches the declared primitive, under the same match table {@see admits()}
     * applies, so a `number` declaration admits an integer entry. Total like every other predicate here: a
     * declaration that is not a translatable lone primitive (a union, an FQCN, a bare `object`, a
     * non-translatable declaration) answers false for every entry, so a caller never credits a value this
     * declaration cannot hold as a language map. A null, list or map entry matches no primitive.
     */
    public function admitsMapEntry(StoredValue $entry): bool
    {
        if (!$this->translatable || !\is_string($this->type)) {
            return false;
        }

        return $this->matchesPrimitive($entry->jsonSerialize(), $this->type);
    }

    /**
     * The primitive types a value must satisfy at least one of, or `null` when the declaration constrains
     * nothing: a bare `object` or an FQCN admits whatever the client authored, and so does a union carrying
     * either, because that member alone accepts every value. A union's declared type is an array, so
     * {@see isPrimitive()} answers false for every one of them; the members are tested against
     * {@see PRIMITIVE_TYPES} here instead.
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
     * Only a map variant whose every entry matches the declared primitive ({@see admitsMapEntry()}). Emptiness
     * needs no test of its own: the empty map is unrepresentable ({@see StoredValue::ofMap()}), and the wire's
     * empty `[]` decodes to the list variant.
     */
    private function admitsLanguageMap(StoredValue $value): bool
    {
        if (!$value->isMap()) {
            return false;
        }

        foreach ($value->asMap() as $entry) {
            if (!$this->admitsMapEntry($entry)) {
                return false;
            }
        }

        return true;
    }

    /**
     * `number` admits an integer as well as a float — JSON carries no distinction a client can be held to —
     * while `integer` admits only an integer.
     */
    private function matchesPrimitive(mixed $raw, string $primitive): bool
    {
        return match ($primitive) {
            'string' => \is_string($raw),
            'integer' => \is_int($raw),
            'number' => \is_int($raw) || \is_float($raw),
            'boolean' => \is_bool($raw),
            default => false,
        };
    }
}
