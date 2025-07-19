<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('discovery')]
class SnippetPaths extends Struct
{
    /**
     * @var list<string>
     */
    private array $paths = [];

    public function add(string $path): void
    {
        $this->paths[] = $path;
    }

    /**
     * @param list<string> $paths
     */
    public function merge(array $paths): void
    {
        $this->paths = array_merge($this->paths, $paths);
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->paths;
    }

    public function empty(): bool
    {
        return empty($this->paths);
    }
}
