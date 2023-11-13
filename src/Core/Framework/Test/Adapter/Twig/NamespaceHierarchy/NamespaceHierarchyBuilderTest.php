<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig\NamespaceHierarchy;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class NamespaceHierarchyBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItAddsAppTemplateNamespaces(): void
    {
        /** @var EntityRepository $appRepository */
        $appRepository = $this->getContainer()->get('app.repository');

        $appRepository->create([
            [
                'name' => 'SwagApp',
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
                    'name' => 'SwagApp',
                ],
            ],
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

        $hierarchyBuilder = $this->getContainer()->get(NamespaceHierarchyBuilder::class);

        $coreHierarchy = [
            'Profiling',
            'Elasticsearch',
            'Administration',
            'Framework',
            'Storefront',
        ];
        // Remove not installed core bundles from hierarchy
        $coreHierarchy = array_intersect($coreHierarchy, array_keys($this->getContainer()->getParameter('kernel.bundles')));

        static::assertSame([
            ...$coreHierarchy,
            'SwagThemeTest',
        ], array_keys($hierarchyBuilder->buildHierarchy()));
    }
}
