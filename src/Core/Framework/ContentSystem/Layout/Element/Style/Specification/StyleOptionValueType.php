<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * Value vocabulary of a single style option: the declared primitive plus its declarative
 * bounds (enum, numeric range, string maxLength) and an advisory default. Style values are
 * always per-breakpoint primitives, so — unlike the type system's PropertyType — there is no
 * FQCN, no nested properties, and no regex (a maxLength bounds strings instead). The primitive
 * set is self-contained here; the type system inlines its own and is not refactored.
 *
 * @internal
 *
 * @phpstan-type StyleRange = array{min?: int|float, max?: int|float}
 * @phpstan-type StyleValueTypeSchema = array{
 *     type: string,
 *     enum: list<string|int|float|bool>|null,
 *     range: StyleRange|null,
 *     maxLength: int|null,
 *     default: string|int|float|bool|null
 * }
 */
#[Package('framework')]
final readonly class StyleOptionValueType
{
    public const TYPE_STRING = 'string';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_NUMBER = 'number';
    public const TYPE_BOOLEAN = 'boolean';

    /**
     * @var list<string>
     */
    public const PRIMITIVE_TYPES = [self::TYPE_STRING, self::TYPE_INTEGER, self::TYPE_NUMBER, self::TYPE_BOOLEAN];

    /**
     * Cap applied to a string option that declares no maxLength, so a client can never store an
     * unbounded string in the layout JSON column.
     */
    public const DEFAULT_STRING_MAX_LENGTH = 255;

    /**
     * @param list<string|int|float|bool>|null $enum
     * @param StyleRange|null $range
     */
    public function __construct(
        private string $type,
        private ?array $enum,
        private ?array $range,
        private ?int $maxLength,
        private string|int|float|bool|null $default,
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return list<string|int|float|bool>|null
     */
    public function enum(): ?array
    {
        return $this->enum;
    }

    /**
     * @return StyleRange|null
     */
    public function range(): ?array
    {
        return $this->range;
    }

    /**
     * The effective string cap: the declared maxLength, or DEFAULT_STRING_MAX_LENGTH for a string
     * option that declares none. Null for non-string types.
     */
    public function maxLength(): ?int
    {
        if ($this->maxLength !== null) {
            return $this->maxLength;
        }

        if ($this->type === self::TYPE_STRING) {
            return self::DEFAULT_STRING_MAX_LENGTH;
        }

        return null;
    }

    public function default(): string|int|float|bool|null
    {
        return $this->default;
    }

    public function isPrimitive(): bool
    {
        return \in_array($this->type, self::PRIMITIVE_TYPES, true);
    }

    /**
     * @return StyleValueTypeSchema
     */
    public function toSchema(): array
    {
        return [
            'type' => $this->type,
            'enum' => $this->enum,
            'range' => $this->range,
            'maxLength' => $this->maxLength(),
            'default' => $this->default,
        ];
    }
}
