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
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());

        foreach ($diFiles as $diFile) {
            $loader->load($diFile);
        }

        (new ContentRouteCompilerPass())->process($container);

        $generatedRouteNames = $this->collectGeneratedRouteNames($container, $sectionKeys);

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
     * The route definitions the pass hands to {@see ContentRouteLoader} carry the route name as
     * constructor argument 1 and the defaults as argument 3, matching
     * {@see \Shopware\Core\Framework\ContentSystem\SalesChannel\Routing\ContentRouteDefinition}'s
     * signature. The section a route belongs to is read from the `_controller` default, which the
     * pass builds from the per-(section, format) route service id — a different mechanism from the
     * route name, so filtering never reuses the naming rule the expected list pins.
     *
     * @param list<string> $sectionKeys
     *
     * @return list<string>
     */
    private function collectGeneratedRouteNames(ContainerBuilder $container, array $sectionKeys): array
    {
        $routeDefinitions = $container->getDefinition(ContentRouteLoader::class)->getArgument(0);
        static::assertIsArray($routeDefinitions);

        $routeNames = [];

        foreach ($routeDefinitions as $routeDefinition) {
            static::assertInstanceOf(Definition::class, $routeDefinition);

            $routeName = $routeDefinition->getArgument(1);
            static::assertIsString($routeName);

            $defaults = $routeDefinition->getArgument(3);
            static::assertIsArray($defaults);
            static::assertArrayHasKey('_controller', $defaults);

            $controller = $defaults['_controller'];
            static::assertIsString($controller);

            foreach ($sectionKeys as $sectionKey) {
                if (!str_starts_with($controller, ContentRoute::class . '.' . $sectionKey . '.')) {
                    continue;
                }

                $routeNames[] = $routeName;

                break;
            }
        }

        sort($routeNames);

        return $routeNames;
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
