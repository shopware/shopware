<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Twig\NamespaceHierarchy;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class BundleHierarchyBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $appRepository;

    protected function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
    }

    public function testItAddsAppNamespace(): void
    {
        $this->appRepository->create([
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
        $this->appRepository->create([
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
        $this->appRepository->create([
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
        $this->appRepository->create([
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
