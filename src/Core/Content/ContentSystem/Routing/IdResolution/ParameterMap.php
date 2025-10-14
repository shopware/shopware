<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class ParameterMap
{
    /**
     * @param array<string, int|string|bool|float> $map Parameter name => Scalar value
     */
    public function __construct(
        private array $map = []
    ) {
        $this->validate();
    }

    public function get(string $name): int|string|bool|float|null
    {
        return $this->map[$name] ?? null;
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
