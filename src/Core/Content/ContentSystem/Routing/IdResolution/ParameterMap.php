<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class ParameterMap
{
    /**
     * @param array<string, int|string|bool|float> $map Parameter name => Scalar value
     */
    public function __construct(
        private readonly array $map = []
    ) {
        $this->validate();
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function get(string $name): int|string|bool|float|null
    {
        return $this->map[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->map[$name]);
    }

    public function add(string $name, int|string|bool|float $value): self
    {
        $map = $this->map;
        $map[$name] = $value;

        return new self($map);
    }

    public function merge(self $other): self
    {
        return new self(array_merge($this->map, $other->map));
    }

    public function isEmpty(): bool
    {
        return empty($this->map);
    }

    /**
     * @return array<string, int|string|bool|float>
     */
    public function toArray(): array
    {
        return $this->map;
    }

    private function validate(): void
    {
        foreach ($this->map as $key => $value) {
            if (!\is_string($key)) {
                throw ContentSystemException::invalidMapKey('Parameter map', get_debug_type($key));
            }

            if (!\is_scalar($value)) {
                throw ContentSystemException::invalidMapValue('Parameter map', $key, 'scalar (int, string, bool, float)', get_debug_type($value));
            }
        }
    }
}
