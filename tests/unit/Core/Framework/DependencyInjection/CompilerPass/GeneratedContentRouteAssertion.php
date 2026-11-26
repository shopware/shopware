<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRoute;
use Shopware\Core\Framework\ContentSystem\SalesChannel\Routing\ContentRouteLoader;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ContentRouteCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Pins the store-api content route surface a bundle's own DI registrations produce.
 *
 * The expected side is always a literal list written into the calling test. It is never derived
 * from the `content_system.section_resolver` / `content_system.output_format` tags, from
 * {@see ContentRouteCompilerPass} or from its route-name builder: a set derived from the same
 * inputs would move with a new tag and the assertion could never fail.
 *
 * The generated side comes from the real DI files, loaded into a bare container and put through
 * the real compiler pass, so no stub service can decide what the test asserts.
 *
 * @internal
 */
#[Package('framework')]
trait GeneratedContentRouteAssertion
{
    /**
     * @param list<string> $expectedRouteNames literal route names, written out by hand
     * @param list<string> $sectionKeys the `content_system.section_resolver` section keys owned by the calling bundle
     * @param string ...$diFiles absolute paths of the DI files that register those resolvers, plus everything they need
     */
    protected function assertGeneratedContentRouteNames(array $expectedRouteNames, array $sectionKeys, string ...$diFiles): void
    {
        $generatedRouteNames = $this->collectGeneratedRouteNames($this->loadContentRouteDefinitions(...$diFiles), $sectionKeys);

        $expectedSorted = $expectedRouteNames;
        sort($expectedSorted);

        $missing = array_values(array_diff($expectedSorted, $generatedRouteNames));
        $unexpected = array_values(array_diff($generatedRouteNames, $expectedSorted));

        static::assertSame($expectedSorted, $generatedRouteNames, \sprintf(
            'The store-api content routes generated for section(s) "%s" do not match the pinned set. Expected but not generated: %s. Generated but not expected: %s.',
            implode('", "', $sectionKeys),
            $this->describeRouteNames($missing),
            $this->describeRouteNames($unexpected),
        ));
    }

    /**
     * Asserts the path and the empty `path` default of every path-less section route the pass generates.
     *
     * The expected side is a literal route-name-to-path map written into the calling test, never derived
     * from the pass. The generated side reads the path from route Definition argument 0 and the defaults
     * from argument 3, so the assertion does not reuse the pass's own branch that decides whether a route
     * has a path.
     *
     * It also asserts, per route, that the controller belongs to the section the route name names: a
     * `store-api.content-header*` route must be wired to a `ContentRoute.header.<format>` controller, so a
     * header route wired to a footer controller service fails.
     *
     * @param array<string, string> $expectedPaths route name => literal route path, written out by hand
     * @param list<string> $sectionKeys the `content_system.section_resolver` section keys owned by the calling bundle
     * @param string ...$diFiles absolute paths of the DI files that register those resolvers, plus everything they need
     */
    protected function assertGeneratedPathLessSectionRoutePaths(array $expectedPaths, array $sectionKeys, string ...$diFiles): void
    {
        $generatedPaths = [];

        foreach ($this->loadContentRouteDefinitions(...$diFiles) as $routeDefinition) {
            $defaults = $routeDefinition->getArgument(3);
            static::assertIsArray($defaults);
            static::assertArrayHasKey('_controller', $defaults);

            $controller = $defaults['_controller'];
            static::assertIsString($controller);

            if (!$this->isSectionController($controller, $sectionKeys)) {
                continue;
            }

            $routeName = $routeDefinition->getArgument(1);
            static::assertIsString($routeName);

            $sectionKey = $this->sectionNamedByRouteName($routeName, $sectionKeys);
            static::assertNotNull($sectionKey, \sprintf(
                'Route "%s" names none of the sections "%s".',
                $routeName,
                implode('", "', $sectionKeys),
            ));

            static::assertStringStartsWith(
                ContentRoute::class . '.' . $sectionKey . '.',
                $controller,
                \sprintf(
                    'Route "%s" names section "%s" but is wired to controller "%s".',
                    $routeName,
                    $sectionKey,
                    $controller,
                ),
            );

            $path = $routeDefinition->getArgument(0);
            static::assertIsString($path);

            static::assertArrayHasKey('path', $defaults, \sprintf(
                'Route "%s" of a path-less section does not default "path" to the empty string.',
                $routeName,
            ));
            static::assertSame('', $defaults['path'], \sprintf(
                'Route "%s" of a path-less section does not default "path" to the empty string.',
                $routeName,
            ));

            $generatedPaths[$routeName] = $path;
        }

        $expectedSorted = $expectedPaths;
        ksort($expectedSorted);
        ksort($generatedPaths);

        static::assertSame($expectedSorted, $generatedPaths, \sprintf(
            'The paths of the path-less store-api content routes generated for section(s) "%s" do not match the pinned map.',
            implode('", "', $sectionKeys),
        ));
    }

    /**
     * Loads the DI files into a bare container and returns the route definitions the real compiler pass
     * hands to {@see ContentRouteLoader}.
     *
     * @return list<Definition>
     */
    private function loadContentRouteDefinitions(string ...$diFiles): array
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());

        foreach ($diFiles as $diFile) {
            $loader->load($diFile);
        }

        (new ContentRouteCompilerPass())->process($container);

        $definitions = $container->getDefinition(ContentRouteLoader::class)->getArgument(0);
        static::assertIsArray($definitions);

        $routeDefinitions = [];

        foreach ($definitions as $definition) {
            static::assertInstanceOf(Definition::class, $definition);

            $routeDefinitions[] = $definition;
        }

        return $routeDefinitions;
    }

    /**
     * The route definitions the pass hands to {@see ContentRouteLoader} carry the route name as
     * constructor argument 1 and the defaults as argument 3, matching
     * {@see \Shopware\Core\Framework\ContentSystem\SalesChannel\Routing\ContentRouteDefinition}'s
     * signature. The section a route belongs to is read from the `_controller` default, which the
     * pass builds from the per-(section, format) route service id — a different mechanism from the
     * route name, so filtering never reuses the naming rule the expected list pins.
     *
     * @param list<Definition> $routeDefinitions
     * @param list<string> $sectionKeys
     *
     * @return list<string>
     */
    private function collectGeneratedRouteNames(array $routeDefinitions, array $sectionKeys): array
    {
        $routeNames = [];

        foreach ($routeDefinitions as $routeDefinition) {
            $defaults = $routeDefinition->getArgument(3);
            static::assertIsArray($defaults);
            static::assertArrayHasKey('_controller', $defaults);

            $controller = $defaults['_controller'];
            static::assertIsString($controller);

            if (!$this->isSectionController($controller, $sectionKeys)) {
                continue;
            }

            $routeName = $routeDefinition->getArgument(1);
            static::assertIsString($routeName);

            $routeNames[] = $routeName;
        }

        sort($routeNames);

        return $routeNames;
    }

    /**
     * @param list<string> $sectionKeys
     */
    private function isSectionController(string $controller, array $sectionKeys): bool
    {
        foreach ($sectionKeys as $sectionKey) {
            if (str_starts_with($controller, ContentRoute::class . '.' . $sectionKey . '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The section key a route name names. The route name carries the section's route path segment, which
     * contains the key as a hyphen- or dot-delimited word: `store-api.content-header.skeleton` names
     * section `header`. This reads the generated name only to decide which pinned section the controller
     * must match; the generated name set is pinned literally by the caller.
     *
     * @param list<string> $sectionKeys
     */
    private function sectionNamedByRouteName(string $routeName, array $sectionKeys): ?string
    {
        $words = preg_split('/[.-]/', $routeName);
        static::assertIsArray($words);

        foreach ($sectionKeys as $sectionKey) {
            if (\in_array($sectionKey, $words, true)) {
                return $sectionKey;
            }
        }

        return null;
    }

    /**
     * @param list<string> $routeNames
     */
    private function describeRouteNames(array $routeNames): string
    {
        if ($routeNames === []) {
            return '<none>';
        }

        return '"' . implode('", "', $routeNames) . '"';
    }
}
