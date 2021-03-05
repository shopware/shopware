<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

class MigrationSource
{
    private const PHP_CLASS_NAME_REGEX = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$';

    /**
     * @var string
     */
    private $name;

    /**
     * @var array<string, string|MigrationSource>
     */
    private $sources;

    /**
     * @var array
     */
    private $replacementPatterns = [];

    public function __construct(string $name, iterable $namespaces = [])
    {
        $this->name = $name;
        $this->sources = $namespaces instanceof \Traversable
            ? iterator_to_array($namespaces)
            : $namespaces;
    }

    public function addDirectory(string $directory, string $namespace): void
    {
        $this->sources[$directory] = $namespace;
    }

    public function addReplacementPattern(string $regexPattern, string $replacePattern): void
    {
        $this->replacementPatterns[] = [$regexPattern, $replacePattern];
    }

    public function getName(): string
    {
        return $this->name;
    }

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

    public function mapToOldName(string $className): ?string
    {
        $replacementPatterns = $this->getReplacementPatterns();

        $oldName = $className;

        foreach ($replacementPatterns as $pattern) {
            $searchPattern = $pattern[0] ?? null;
            $replacePattern = $pattern[1] ?? null;

            if (\is_string($searchPattern) && \is_string($replacePattern)) {
                $oldName = preg_replace($searchPattern, $replacePattern, (string) $oldName);
            }
        }

        if ($oldName === $className) {
            return null;
        }

        return $oldName;
    }

    public function getReplacementPatterns(): array
    {
        $patterns = $this->replacementPatterns;

        foreach ($this->sources as $namespace) {
            if ($namespace instanceof MigrationSource) {
                $patterns = array_merge($patterns, $namespace->getReplacementPatterns());
            }
        }

        return $patterns;
    }
}
