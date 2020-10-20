<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig\NamespaceHierarchy;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class NamespaceHierarchyBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItAddsAppTemplateNamespaces(): void
    {
        /** @var EntityRepositoryInterface $appRepository */
        $appRepository = $this->getContainer()->get('app.repository');

        $appRepository->create([
            [
                'name' => 'SwagApp',
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
                    'name' => 'SwagApp',
                ],
            ],
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

        $hierarchyBuilder = $this->getContainer()->get(NamespaceHierarchyBuilder::class);

        static::assertEquals([
            'SwagThemeTest',
            'Storefront',
            'Administration',
            'Framework',
        ], $hierarchyBuilder->buildHierarchy());
    }
}
