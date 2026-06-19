<?php declare(strict_types=1);

namespace Shopware\Tests\Devops\Core\Framework\Api;

use Composer\ClassMapGenerator\ClassMapGenerator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class ApiAliasTest extends TestCase
{
    use KernelTestBehaviour;

    public function testUniqueAliases(): void
    {
        $entities = self::getContainer()->get(DefinitionInstanceRegistry::class)
            ->getDefinitions();

        $aliases = array_flip(array_keys($entities));
        $count = \count($aliases);

        $duplicates = [];

        foreach ($this->discoverShopwareClasses() as $class) {
            if (!is_subclass_of($class, Struct::class)) {
                continue;
            }

            if (is_subclass_of($class, Aggregation::class) || is_subclass_of($class, AggregationResult::class)) {
                continue;
            }

            if (is_subclass_of($class, Entity::class)) {
                continue;
            }

            $reflector = new \ReflectionClass($class);

            if ($reflector->isAbstract()) {
                continue;
            }

            $instance = $reflector->newInstanceWithoutConstructor();

            $alias = $instance->getApiAlias();

            if ($alias === 'aggregation-' || $alias === 'dal_entity_search_result') {
                continue;
            }

            if (isset($aliases[$alias])) {
                $duplicates[$alias][] = $class;
                continue;
            }

            $aliases[$alias] = $class;
        }

        static::assertTrue(\count($aliases) > $count, 'Validated only entities, please check registered classes of class loader');

        static::assertSame(
            [],
            $duplicates,
            "Duplicate API aliases detected:\n" . $this->formatDuplicates($duplicates, $aliases)
        );
    }

    /**
     * @param array<string, list<string>> $duplicates
     * @param array<string, mixed> $aliases
     */
    private function formatDuplicates(array $duplicates, array $aliases): string
    {
        $lines = [];
        foreach ($duplicates as $alias => $classes) {
            $first = \is_string($aliases[$alias] ?? null) ? $aliases[$alias] : '(entity definition)';
            $lines[] = \sprintf('  "%s" — first: %s; also: %s', $alias, $first, implode(', ', $classes));
        }

        return implode("\n", $lines);
    }

    /**
     * Walk every PSR-4 prefix under the `Shopware\` namespace and yield each
     * discoverable class FQN. Replaces the previous reliance on
     * `ClassLoader::getClassMap()`, which is only populated when Composer is
     * run with `--optimize-autoloader` / `--classmap-authoritative`.
     *
     * @return iterable<string>
     */
    private function discoverShopwareClasses(): iterable
    {
        $classLoader = KernelLifecycleManager::getClassLoader();
        $generator = new ClassMapGenerator();
        $excludedDirs = ['node_modules', 'Resources', 'Patch'];

        foreach ($classLoader->getPrefixesPsr4() as $prefix => $directories) {
            if (!str_starts_with($prefix, 'Shopware\\')) {
                continue;
            }

            // Skip test PSR-4 roots; mirrors the original `exclude-from-classmap: tests/`.
            if (str_starts_with($prefix, 'Shopware\\Tests\\')) {
                continue;
            }

            foreach ($directories as $directory) {
                if (!is_dir($directory)) {
                    continue;
                }

                $generator->scanPaths($directory, null, 'psr-4', $prefix, $excludedDirs);
            }
        }

        foreach (array_keys($generator->getClassMap()->getMap()) as $class) {
            try {
                if (!class_exists($class)) {
                    continue;
                }
            } catch (\Throwable) {
                // Class can't be loaded in this environment (missing extension,
                // missing optional parent class, etc.). The original classmap-based
                // walk skipped these silently because they were never registered.
                continue;
            }

            yield $class;
        }
    }
}
