<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * One property value of a {@see StoredElement}, wrapped so that a hydrated object can never sit in
 * storage: the payload is `scalar|null|list<StoredValue>|array<array-key, StoredValue>` at every depth,
 * which makes "an entity in the stored tree" a static type error instead of a runtime tree walk.
 *
 * The typed named constructors and {@see fromDecoded()} are the only ingress for a raw PHP value; every
 * other operation takes and returns wrapped values.
 */
#[Package('framework')]
final readonly class StoredValue implements \JsonSerializable
{
    private const VARIANT_NULL = 'null';

    private const VARIANT_STRING = 'string';

    private const VARIANT_INT = 'int';

    private const VARIANT_FLOAT = 'float';

    private const VARIANT_BOOL = 'bool';

    private const VARIANT_LIST = 'list';

    private const VARIANT_MAP = 'map';

    /**
     * @param array<array-key, self> $items
     */
    private function __construct(
        private string $variant,
        private string|int|float|bool|null $scalar,
        private array $items,
    ) {
    }

    public static function ofNull(): self
    {
        return new self(self::VARIANT_NULL, null, []);
    }

    public static function ofString(string $value): self
    {
        return new self(self::VARIANT_STRING, $value, []);
    }

    public static function ofInt(int $value): self
    {
        return new self(self::VARIANT_INT, $value, []);
    }

    /**
     * NAN and INF are scalars but are not JSON-encodable, so they cannot survive a storage round trip
     * and are rejected here rather than at the column.
     */
    public static function ofFloat(float $value): self
    {
        if (!\is_finite($value)) {
            throw ContentSystemException::invalidFieldValueType('StoredValue', 'finite float', (string) $value);
        }

        return new self(self::VARIANT_FLOAT, $value, []);
    }

    public static function ofBool(bool $value): self
    {
        return new self(self::VARIANT_BOOL, $value, []);
    }

    /**
     * @param list<self> $values
     */
    public static function ofList(array $values): self
    {
        return new self(self::VARIANT_LIST, null, $values);
    }

    /**
     * @param array<array-key, self> $values
     */
    public static function ofMap(array $values): self
    {
        return new self(self::VARIANT_MAP, null, $values);
    }

    /**
     * Wraps a raw decoded PHP value recursively. A JSON-decoded array is a list variant when its keys are
     * a zero-based sequence and a map variant otherwise, matching how `json_decode($json, true)` reports
     * the two shapes; an empty array is therefore a list.
     */
    public static function fromDecoded(mixed $value): self
    {
        if ($value === null) {
            return self::ofNull();
        }

        if (\is_string($value)) {
            return self::ofString($value);
        }

        if (\is_int($value)) {
            return self::ofInt($value);
        }

        if (\is_float($value)) {
            return self::ofFloat($value);
        }

        if (\is_bool($value)) {
            return self::ofBool($value);
        }

        if (\is_array($value)) {
            $wrapped = array_map(self::fromDecoded(...), $value);

            if (array_is_list($value)) {
                return self::ofList(array_values($wrapped));
            }

            return self::ofMap($wrapped);
        }

        throw ContentSystemException::invalidFieldValueType('StoredValue', 'scalar, null or array', get_debug_type($value));
    }

    public function isNull(): bool
    {
        return $this->variant === self::VARIANT_NULL;
    }

    public function isString(): bool
    {
        return $this->variant === self::VARIANT_STRING;
    }

    public function asString(): string
    {
        if (!\is_string($this->scalar)) {
            throw ContentSystemException::invalidFieldType(self::VARIANT_STRING, $this->variant);
        }

        return $this->scalar;
    }

    public function asInt(): int
    {
        if (!\is_int($this->scalar)) {
            throw ContentSystemException::invalidFieldType(self::VARIANT_INT, $this->variant);
        }

        return $this->scalar;
    }

    public function asFloat(): float
    {
        if (!\is_float($this->scalar)) {
            throw ContentSystemException::invalidFieldType(self::VARIANT_FLOAT, $this->variant);
        }

        return $this->scalar;
    }

    public function asBool(): bool
    {
        if (!\is_bool($this->scalar)) {
            throw ContentSystemException::invalidFieldType(self::VARIANT_BOOL, $this->variant);
        }

        return $this->scalar;
    }

    /**
     * @return list<self>
     */
    public function asList(): array
    {
        if ($this->variant !== self::VARIANT_LIST) {
            throw ContentSystemException::invalidFieldType(self::VARIANT_LIST, $this->variant);
        }

        return array_values($this->items);
    }

    /**
     * @return array<array-key, self>
     */
    public function asMap(): array
    {
        if ($this->variant !== self::VARIANT_MAP) {
            throw ContentSystemException::invalidFieldType(self::VARIANT_MAP, $this->variant);
        }

        return $this->items;
    }

    /**
     * The canonical comparison: scalars compare with `===` (so `0`, `'0'` and `false` are three different
     * values), lists compare positionally, maps compare per key with key order irrelevant, and a list never
     * equals a map. Loader-config canonicalization value-sorts lists and is deliberately not reused here,
     * because a stored list's order is authored content.
     */
    public function equals(self $other): bool
    {
        if ($this->variant !== $other->variant) {
            return false;
        }

        if ($this->variant === self::VARIANT_LIST) {
            return $this->listEquals($other->items);
        }

        if ($this->variant === self::VARIANT_MAP) {
            return $this->mapEquals($other->items);
        }

        return $this->scalar === $other->scalar;
    }

    /**
     * Unwraps recursively into the raw PHP value the storage column, the admin responses and the rendered
     * seam all read. An empty map unwraps to `[]`, never to an object: the DAL validates these fields as
     * arrays, so `[]` is the single canonical empty shape.
     */
    public function jsonSerialize(): mixed
    {
        if ($this->variant === self::VARIANT_LIST || $this->variant === self::VARIANT_MAP) {
            return array_map(static fn (self $item): mixed => $item->jsonSerialize(), $this->items);
        }

        return $this->scalar;
    }

    /**
     * @param array<array-key, self> $other
     */
    private function listEquals(array $other): bool
    {
        if (\count($this->items) !== \count($other)) {
            return false;
        }

        $theirs = array_values($other);

        foreach (array_values($this->items) as $index => $item) {
            if (!$item->equals($theirs[$index])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<array-key, self> $other
     */
    private function mapEquals(array $other): bool
    {
        if (\count($this->items) !== \count($other)) {
            return false;
        }

        foreach ($this->items as $key => $item) {
            if (!\array_key_exists($key, $other)) {
                return false;
            }

            if (!$item->equals($other[$key])) {
                return false;
            }
        }

        return true;
    }
}
