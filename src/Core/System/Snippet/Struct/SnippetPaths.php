<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('discovery')]
class SnippetPaths extends Struct implements \Countable
{
    /**
     * @var list<string>
     */
    private array $paths = [];

    public function add(string $path): void
    {
        if ($this->has($path)) {
            return;
        }

        $this->paths[] = $path;
    }

    public function has(string $path): bool
    {
        return \in_array($path, $this->paths, true);
    }

    /**
     * @param list<string> $paths
     */
    public function merge(array $paths): void
    {
        foreach ($paths as $path) {
            $this->add($path);
        }
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->paths;
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, use `isEmpty` instead
     */
    public function empty(): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0', 'The method `empty` will be removed, use `isEmpty` instead.');

        return $this->isEmpty();
    }

    public function isEmpty(): bool
    {
        return empty($this->paths);
    }

    public function count(): int
    {
        return \count($this->paths);
    }
}
