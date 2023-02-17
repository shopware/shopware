<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig\NamespaceHierarchy;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
class BundleHierarchyBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItAddsAppNamespace(): void
    {
        /** @var EntityRepository $appRepository */
        $appRepository = $this->getContainer()->get('app.repository');

        $appRepository->create([
            [
                'name' => 'SwagThemeTest',
                'active' => true,
                'path' => __DIR__ . '/Manifest/_fixtures/test',
                'version' => '0.0.1',
                'label' => 'test',
                'accessToken' => 'test',
                'templateLoadPriority' => 2,
                'integration' => [
                    'label' => 'test',
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'SwagThemeTest',
                ],
                'templates' => [
                    [
                        'template' => 'test',
                        'path' => 'storefront/base.html.twig',
                        'active' => true,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $bundleHierarchyBuilder = $this->getContainer()->get(BundleHierarchyBuilder::class);

        $coreHierarchy = $this->getCoreNamespaceHierarchy();

        static::assertSame([
            ...$coreHierarchy,
            'SwagThemeTest',
        ], array_keys($bundleHierarchyBuilder->buildNamespaceHierarchy([])));
    }

    public function testItExcludesInactiveApps(): void
    {
        /** @var EntityRepository $appRepository */
        $appRepository = $this->getContainer()->get('app.repository');

        $appRepository->create([
            [
                'name' => 'SwagThemeTest',
                'path' => __DIR__ . '/Manifest/_fixtures/test',
                'version' => '0.0.1',
                'label' => 'test',
                'accessToken' => 'test',
                'integration' => [
                    'label' => 'test',
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'SwagThemeTest',
                ],
                'templates' => [
                    [
                        'template' => 'test',
                        'path' => 'storefront/base.html.twig',
                        'active' => true,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $bundleHierarchyBuilder = $this->getContainer()->get(BundleHierarchyBuilder::class);

        static::assertSame($this->getCoreNamespaceHierarchy(), array_keys($bundleHierarchyBuilder->buildNamespaceHierarchy([])));
    }

    public function testItExcludesInactiveAppTemplates(): void
    {
        /** @var EntityRepository $appRepository */
        $appRepository = $this->getContainer()->get('app.repository');

        $appRepository->create([
            [
                'name' => 'SwagThemeTest',
                'active' => true,
                'path' => __DIR__ . '/Manifest/_fixtures/test',
                'version' => '0.0.1',
                'label' => 'test',
                'accessToken' => 'test',
                'integration' => [
                    'label' => 'test',
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'SwagThemeTest',
                ],
                'templates' => [
                    [
                        'template' => 'test',
                        'path' => 'storefront/base.html.twig',
                        'active' => false,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $bundleHierarchyBuilder = $this->getContainer()->get(BundleHierarchyBuilder::class);

        static::assertSame($this->getCoreNamespaceHierarchy(), array_keys($bundleHierarchyBuilder->buildNamespaceHierarchy([])));
    }

    public function testItExcludesAppNamespacesWithNoTemplates(): void
    {
        /** @var EntityRepository $appRepository */
        $appRepository = $this->getContainer()->get('app.repository');

        $appRepository->create([
            [
                'name' => 'SwagThemeTest',
                'path' => __DIR__ . '/Manifest/_fixtures/test',
                'version' => '0.0.1',
                'label' => 'test',
                'accessToken' => 'test',
                'integration' => [
                    'label' => 'test',
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'SwagThemeTest',
                ],
                'templates' => [],
            ],
        ], Context::createDefaultContext());

        $bundleHierarchyBuilder = $this->getContainer()->get(BundleHierarchyBuilder::class);

        static::assertSame($this->getCoreNamespaceHierarchy(), array_keys($bundleHierarchyBuilder->buildNamespaceHierarchy([])));
    }

    /**
     * @dataProvider sortingProvider
     *
     * @param array<string, int> $plugins
     * @param array<string, int> $apps
     * @param array<int, string> $expectedSorting
     */
    public function testSortingOfTemplates(array $plugins, array $apps, array $expectedSorting): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $bundles = [];

        foreach ($plugins as $plugin => $prio) {
            $bundles[] = new MockBundle($plugin, $prio, __DIR__ . '/../fixtures/Plugins/TestPlugin1/');
        }

        $kernel->method('getBundles')->willReturn($bundles);

        $connection = $this->createMock(Connection::class);

        $dbApps = [];

        foreach ($apps as $app => $prio) {
            $dbApps[$app] = [
                'version' => '1.0.0',
                'template_load_priority' => $prio,
            ];
        }

        $connection->method('fetchAllAssociativeIndexed')->willReturn($dbApps);

        $builder = new BundleHierarchyBuilder(
            $kernel,
            $connection
        );

        static::assertSame($expectedSorting, array_keys($builder->buildNamespaceHierarchy([])));
    }

    /**
     * @return iterable<string, array<array<int|string, int|string>>>
     */
    public static function sortingProvider(): iterable
    {
        yield 'all with default prio' => [
            ['TestPluginB' => 0, 'TestPluginA' => 0],
            ['TestPluginAppB' => 0, 'TestPluginAppA' => 0],
            ['TestPluginAppB', 'TestPluginAppA', 'TestPluginA', 'TestPluginB'],
        ];

        yield 'one plugin with high prio' => [
            ['TestPluginB' => -500, 'TestPluginA' => 0],
            ['TestPluginAppB' => 0, 'TestPluginAppA' => 0],
            ['TestPluginB', 'TestPluginAppB', 'TestPluginAppA', 'TestPluginA'],
        ];

        yield 'both plugin with high prio to get higher than apps' => [
            ['TestPluginB' => -500, 'TestPluginA' => -400],
            ['TestPluginAppB' => 0, 'TestPluginAppA' => 0],
            ['TestPluginB', 'TestPluginA', 'TestPluginAppB', 'TestPluginAppA'],
        ];

        yield 'mixed prio by apps and extensions' => [
            ['TestPluginB' => -500, 'TestPluginA' => -400],
            ['TestPluginAppB' => -600, 'TestPluginAppA' => 0],
            ['TestPluginAppB', 'TestPluginB', 'TestPluginA', 'TestPluginAppA'],
        ];

        yield 'anyone has priority' => [
            ['TestPluginB' => -500, 'TestPluginA' => -400],
            ['TestPluginAppB' => -600, 'TestPluginAppA' => -700],
            ['TestPluginAppA', 'TestPluginAppB', 'TestPluginB', 'TestPluginA'],
        ];

        yield 'same priority the database order matters' => [
            ['TestPluginB' => -500, 'TestPluginA' => -400],
            ['TestPluginAppB' => -600, 'TestPluginAppA' => -600],
            ['TestPluginAppB', 'TestPluginAppA', 'TestPluginB', 'TestPluginA'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getCoreNamespaceHierarchy(): array
    {
        $coreHierarchy = [
            'Profiling',
            'Elasticsearch',
            'Administration',
            'Framework',
            'Storefront',
        ];
        // Remove not installed core bundles from hierarchy
        return array_values(
            array_intersect(
                $coreHierarchy,
                array_keys($this->getContainer()->getParameter('kernel.bundles'))
            )
        );
    }
}

/**
 * @internal
 */
class MockBundle extends Bundle
{
    public function __construct(
        string $name,
        private readonly int $templatePriority,
        string $path
    ) {
        $this->name = $name;
        $this->path = $path;
    }

    public function getTemplatePriority(): int
    {
        return $this->templatePriority;
    }
}
