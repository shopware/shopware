<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig\NamespaceHierarchy;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class BundleHierarchyBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItAddsAppNamespace(): void
    {
        /** @var EntityRepositoryInterface $appRepository */
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
                    'writeAccess' => false,
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

        static::assertEquals(array_merge(
            ['SwagThemeTest'],
            $this->getCoreNamespaceHierarchy()
        ), array_keys($bundleHierarchyBuilder->buildNamespaceHierarchy([])));
    }

    public function testItExcludesInactiveApps(): void
    {
        /** @var EntityRepositoryInterface $appRepository */
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
                    'writeAccess' => false,
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

        static::assertEquals($this->getCoreNamespaceHierarchy(), array_keys($bundleHierarchyBuilder->buildNamespaceHierarchy([])));
    }

    public function testItExcludesInactiveAppTemplates(): void
    {
        /** @var EntityRepositoryInterface $appRepository */
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
                    'writeAccess' => false,
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

        static::assertEquals($this->getCoreNamespaceHierarchy(), array_keys($bundleHierarchyBuilder->buildNamespaceHierarchy([])));
    }

    public function testItExcludesAppNamespacesWithNoTemplates(): void
    {
        /** @var EntityRepositoryInterface $appRepository */
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
                    'writeAccess' => false,
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

        static::assertEquals($this->getCoreNamespaceHierarchy(), array_keys($bundleHierarchyBuilder->buildNamespaceHierarchy([])));
    }

    /**
     * @return string[]
     */
    private function getCoreNamespaceHierarchy(): array
    {
        $coreHierarchy = [
            'Elasticsearch',
            'Storefront',
            'Administration',
            'Framework',
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
