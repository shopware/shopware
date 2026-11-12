<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * Immutable value vocabulary of a single style option: a primitive type plus its declarative bounds
 * and an advisory default.
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

    public const PRIMITIVE_TYPES = [self::TYPE_STRING, self::TYPE_INTEGER, self::TYPE_NUMBER, self::TYPE_BOOLEAN];

    /**
     * Cap applied to a string or number option that declares no maxLength, so a client can never store an
     * unbounded value (including a long numeric string) in the layout JSON column.
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
     * The effective length cap: the declared maxLength, or DEFAULT_STRING_MAX_LENGTH for a string or number
     * option that declares none. Null for integer and boolean, whose serialized form cannot exceed the cap.
     */
    public function maxLength(): ?int
    {
        if ($this->maxLength !== null) {
            return $this->maxLength;
        }

        // A numeric string would pass the is_numeric type check at unbounded length, so cap it like a string
        // (see DEFAULT_STRING_MAX_LENGTH).
        if ($this->type === self::TYPE_STRING || $this->type === self::TYPE_NUMBER) {
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
