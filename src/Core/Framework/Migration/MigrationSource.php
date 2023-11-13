<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class MigrationSource
{
    private const PHP_CLASS_NAME_REGEX = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$';

    /**
     * @var array<string|MigrationSource>
     */
    private array $sources;

    /**
     * @internal
     *
     * @param iterable<string|MigrationSource> $namespaces
     */
    public function __construct(
        private readonly string $name,
        iterable $namespaces = []
    ) {
        $this->sources = $namespaces instanceof \Traversable
            ? iterator_to_array($namespaces)
            : $namespaces;
    }

    public function addDirectory(string $directory, string $namespace): void
    {
        $this->sources[$directory] = $namespace;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string>
     */
    public function getSourceDirectories(): array
    {
        $sources = [];

        foreach ($this->sources as $directory => $namespace) {
            if ($namespace instanceof MigrationSource) {
                $sources = array_merge($sources, $namespace->getSourceDirectories());
            } else {
                $sources[$directory] = $namespace;
            }
        }

        return $sources;
    }

    public function getNamespacePattern(): string
    {
        $patterns = [];

        foreach ($this->getSourceDirectories() as $namespace) {
            $patterns[] = '^' . str_ireplace('\\', '\\\\', $namespace) . '\\\\' . self::PHP_CLASS_NAME_REGEX;
        }

        // match no migrations, if there are no patterns
        if ($patterns === []) {
            return '(FALSE)';
        }

        if (\count($patterns) === 1) {
            return $patterns[0];
        }

        return '(' . implode('|', $patterns) . ')';
    }
}
