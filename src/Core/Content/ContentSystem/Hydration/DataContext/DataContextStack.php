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
     * @var array<string, array<int, DataContextStackItem>>
     */
    private array $stack = [];

    public function push(string $key, mixed $data, string $distribution): void
    {
        if (!isset($this->stack[$key])) {
            $this->stack[$key] = [];
        }

        $this->stack[$key][] = new DataContextStackItem($data, $distribution);
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

    public function get(string $key): ?DataContextStackItem
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

    public function getDepth(string $key): int
    {
        return isset($this->stack[$key]) ? \count($this->stack[$key]) : 0;
    }

    public function clear(): void
    {
        $this->stack = [];
    }
}
