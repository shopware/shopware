<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Shopware\Core\Framework\Feature;

/**
 * @package core
 */
class MigrationSource
{
    private const PHP_CLASS_NAME_REGEX = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$';

    /**
     * @var string
     */
    private $name;

    /**
     * @var array<string|MigrationSource>
     */
    private $sources;

    /**
     * @deprecated tag:v6.6.0 - Property will be removed
     *
     * @var list<array{0: string, 1: string}>
     */
    private $replacementPatterns = [];

    /**
     * @internal
     *
     * @param iterable<string|MigrationSource> $namespaces
     */
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

    /**
     * @deprecated tag:v6.6.0 - Will be removed, as all migrations are now namespaced by their major version
     */
    public function addReplacementPattern(string $regexPattern, string $replacePattern): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.6.0.0')
        );

        $this->replacementPatterns[] = [$regexPattern, $replacePattern];
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

    /**
     * @deprecated tag:v6.6.0 - Will be removed, as all migrations are now namespaced by their major version
     */
    public function mapToOldName(string $className): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.6.0.0')
        );

        $replacementPatterns = $this->getReplacementPatterns();

        $oldName = $className;

        foreach ($replacementPatterns as $pattern) {
            $searchPattern = $pattern[0];
            $replacePattern = $pattern[1];

            $oldName = preg_replace($searchPattern, $replacePattern, (string) $oldName);
        }

        if ($oldName === $className) {
            return null;
        }

        return $oldName;
    }

    /**
     * @deprecated tag:v6.6.0 - Will be removed, as all migrations are now namespaced by their major version
     *
     * @return list<array{0: string, 1: string}>
     */
    public function getReplacementPatterns(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.6.0.0')
        );

        $patterns = $this->replacementPatterns;

        foreach ($this->sources as $namespace) {
            if ($namespace instanceof MigrationSource) {
                $patterns = array_merge($patterns, $namespace->getReplacementPatterns());
            }
        }

        return $patterns;
    }
}
