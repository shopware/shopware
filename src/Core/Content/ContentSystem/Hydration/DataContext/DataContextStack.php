<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext;

use Shopware\Core\Framework\Log\Package;

/**
 * Enables proper scoping where inner providers shadow outer providers.
 *
 * @internal
 */
#[Package('discovery')]
class DataContextStack
{
    /**
     * @var array<string, array<int, array{data: mixed, distribution: string}>>
     */
    private array $stack = [];

    public function push(string $key, mixed $data, string $distribution): void
    {
        if (!isset($this->stack[$key])) {
            $this->stack[$key] = [];
        }

        $this->stack[$key][] = [
            'data' => $data,
            'distribution' => $distribution,
        ];
    }

    public function pop(string $key): void
    {
        if (!isset($this->stack[$key])) {
            return;
        }

        array_pop($this->stack[$key]);

        if (empty($this->stack[$key])) {
            unset($this->stack[$key]);
        }
    }

    /**
     * @return array{data: mixed, distribution: string}|null
     */
    public function get(string $key): ?array
    {
        if (!isset($this->stack[$key]) || empty($this->stack[$key])) {
            return null;
        }

        return end($this->stack[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->stack[$key]) && !empty($this->stack[$key]);
    }

    /**
     * @internal
     */
    public function getDepth(string $key): int
    {
        return isset($this->stack[$key]) ? \count($this->stack[$key]) : 0;
    }

    /**
     * @internal
     */
    public function clear(): void
    {
        $this->stack = [];
    }
}
