<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Output\Index\LoaderValueIdentityFactory;
use Shopware\Core\Framework\Log\Package;

/**
 * The already-resolved inputs of one loader invocation: one entry per key the loader declared. A null value
 * means the input is unresolved; a key that is absent from the map was never declared and reading it is a
 * loader authoring bug, not a runtime condition.
 */
#[Package('framework')]
final readonly class LoaderInputs
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private array $values)
    {
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * The whole resolved map, for a caller that must characterise the invocation rather than read one input:
     * {@see LoaderValueIdentityFactory} hashes it so two
     * loads of one source with different inputs cannot share a response reference. Not for loaders — they read
     * declared keys through the typed accessors, which is what keeps an undeclared read an authoring error.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function get(string $key): mixed
    {
        if (!\array_key_exists($key, $this->values)) {
            throw ContentSystemException::loaderInputNotDeclared($key, array_keys($this->values));
        }

        return $this->values[$key];
    }

    public function string(string $key): string
    {
        $value = $this->resolvedValue($key);

        if (!\is_string($value)) {
            throw ContentSystemException::loaderInputTypeMismatch($key, 'string', \get_debug_type($value));
        }

        return $value;
    }

    public function int(string $key): int
    {
        $value = $this->resolvedValue($key);

        if (!\is_int($value)) {
            throw ContentSystemException::loaderInputTypeMismatch($key, 'int', \get_debug_type($value));
        }

        return $value;
    }

    public function bool(string $key): bool
    {
        $value = $this->resolvedValue($key);

        if (!\is_bool($value)) {
            throw ContentSystemException::loaderInputTypeMismatch($key, 'bool', \get_debug_type($value));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    public function stringList(string $key): array
    {
        return $this->toStringList($key, $this->resolvedValue($key));
    }

    public function stringOrNull(string $key): ?string
    {
        $value = $this->get($key);

        if ($value === null) {
            return null;
        }

        if (!\is_string($value)) {
            throw ContentSystemException::loaderInputTypeMismatch($key, 'string', \get_debug_type($value));
        }

        return $value;
    }

    public function intOrNull(string $key): ?int
    {
        $value = $this->get($key);

        if ($value === null) {
            return null;
        }

        if (!\is_int($value)) {
            throw ContentSystemException::loaderInputTypeMismatch($key, 'int', \get_debug_type($value));
        }

        return $value;
    }

    /**
     * @return list<string>|null
     */
    public function stringListOrNull(string $key): ?array
    {
        $value = $this->get($key);

        if ($value === null) {
            return null;
        }

        return $this->toStringList($key, $value);
    }

    private function resolvedValue(string $key): mixed
    {
        $value = $this->get($key);

        if ($value === null) {
            throw ContentSystemException::loaderInputUnresolved($key);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function toStringList(string $key, mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw ContentSystemException::loaderInputTypeMismatch($key, 'list<string>', \get_debug_type($value));
        }

        foreach ($value as $entry) {
            if (!\is_string($entry)) {
                throw ContentSystemException::loaderInputTypeMismatch($key, 'list<string>', 'array');
            }
        }

        return $value;
    }
}
