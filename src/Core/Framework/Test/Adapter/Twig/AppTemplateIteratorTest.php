<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\AppTemplateIterator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class AppTemplateIteratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testConstruct(): void
    {
        // somehow the constructor is not marked as covered if we get the service from DI
        $iterator = new AppTemplateIterator(
            $this->getContainer()->get('twig.template_iterator'),
            $this->getContainer()->get('app_template.repository')
        );

        static::assertInstanceOf(AppTemplateIterator::class, $iterator);
    }

    public function testItAddsAppDatabaseTemplates(): void
    {
        /** @var EntityRepository $appRepository */
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
                    'accessKey' => 'test',
                    'secretAccessKey' => 'test',
                ],
                'aclRole' => [
                    'name' => 'SwagApp',
                ],
                'templates' => [
                    [
                        'template' => 'test',
                        'path' => 'storefront/test/base.html.twig',
                        'active' => true,
                    ],
                    [
                        'template' => 'test',
                        'path' => 'storefront/test/active.html.twig',
                        'active' => true,
                    ],
                    [
                        'template' => 'test',
                        'path' => 'storefront/test/deactive.html.twig',
                        'active' => false,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $templateIterator = $this->getContainer()->get(AppTemplateIterator::class);

        $templates = iterator_to_array($templateIterator);

        static::assertContains('storefront/test/base.html.twig', $templates);
        static::assertContains('storefront/test/active.html.twig', $templates);
        static::assertNotContains('storefront/test/deactive.html.twig', $templates);
    }
}
