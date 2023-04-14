<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Error\LoaderError;

/**
 * @internal
 */
class EntityTemplateLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FIRST_TEMPLATE = '
        {% block base_navigation %}
            parent()
                <h1>My awesome Theme</h1>
        {% endblock %}
    ';

    private const SECOND_TEMPLATE = '
        {% block base_breadcrumb %}
            <p>Who needs a breadcrumb?</p>
        {% endblock %}
    ';

    private EntityRepository $templateRepository;

    private EntityTemplateLoader $templateLoader;

    private string $template1Id;

    private string $template2Id;

    protected function setUp(): void
    {
        $this->templateRepository = $this->getContainer()->get('app_template.repository');
        $this->templateLoader = $this->getContainer()->get(EntityTemplateLoader::class);
        $this->template1Id = Uuid::randomHex();
        $this->template2Id = Uuid::randomHex();
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertEquals(
            ['app_template.written' => 'reset'],
            EntityTemplateLoader::getSubscribedEvents()
        );
    }

    public function testGetSourceContextThrowsExceptionIfTemplateIsNotFound(): void
    {
        static::expectException(LoaderError::class);
        $this->templateLoader->getSourceContext('@TestTheme/storefront/base.html.twig');
    }

    public function testGetSourceContext(): void
    {
        $this->importTemplates();
        $source = $this->templateLoader->getSourceContext('@TestTheme/storefront/base.html.twig');

        static::assertEquals(self::FIRST_TEMPLATE, $source->getCode());
        static::assertEquals('@TestTheme/storefront/base.html.twig', $source->getName());
    }

    public function testGetSourceContextForDeactivatedApp(): void
    {
        $this->importTemplates();

        static::expectException(LoaderError::class);
        $this->templateLoader->getSourceContext('@StorefrontTheme/storefront/base.html.twig');
    }

    public function testGetCacheKey(): void
    {
        static::assertEquals(
            '@TestTheme/storefront/base.html.twig',
            $this->templateLoader->getCacheKey('@TestTheme/storefront/base.html.twig')
        );
    }

    public function testIsFreshIfTemplateNotFound(): void
    {
        static::assertFalse(
            $this->templateLoader->isFresh('@TestTheme/storefront/base.html.twig', (new \DateTime())->getTimestamp())
        );
    }

    public function testIsFresh(): void
    {
        $this->importTemplates();
        static::assertTrue(
            $this->templateLoader->isFresh('@TestTheme/storefront/base.html.twig', (new \DateTime())->getTimestamp())
        );

        $beforeUpdate = (new \DateTime())->getTimestamp();

        $this->templateRepository->update([
            [
                'id' => $this->template1Id,
                'template' => '
                    {% block base_navigation %}
                        parent()
                            <h1>My updated Theme</h1>
                    {% endblock %}
                ',
            ],
        ], Context::createDefaultContext());

        static::assertFalse(
            $this->templateLoader->isFresh('@TestTheme/storefront/base.html.twig', $beforeUpdate)
        );
        static::assertTrue(
            $this->templateLoader->isFresh('@TestTheme/storefront/base.html.twig', (new \DateTime())->getTimestamp() + 1)
        );
    }

    public function testExists(): void
    {
        static::assertFalse(
            $this->templateLoader->exists('@TestTheme/storefront/base.html.twig')
        );

        $this->importTemplates();

        static::assertTrue(
            $this->templateLoader->exists('@TestTheme/storefront/base.html.twig')
        );
        static::assertFalse(
            $this->templateLoader->exists('storefront/base.html.twig')
        );
    }

    public function testExistsForDeactivatedApp(): void
    {
        static::assertFalse(
            $this->templateLoader->exists('@StorefrontTheme/storefront/base.html.twig')
        );

        $this->importTemplates();

        static::assertFalse(
            $this->templateLoader->exists('@StorefrontTheme/storefront/base.html.twig')
        );
    }

    public function testTemplateLoadingIsCachedWithoutDatabaseTemplates(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $templateLoader = new EntityTemplateLoader($connection, 'prod');

        static::assertFalse($templateLoader->exists('@Storefront/storefront/base.html.twig'));
        static::assertFalse($templateLoader->exists('@Storefront/storefront/test.html.twig'));
    }

    private function importTemplates(): void
    {
        $this->templateRepository->upsert([
            [
                'id' => $this->template1Id,
                'path' => 'storefront/base.html.twig',
                'active' => true,
                'template' => self::FIRST_TEMPLATE,
                'app' => [
                    'name' => 'TestTheme',
                    'path' => __DIR__ . '/../../../Content/App/Manifest/_fixtures/test',
                    'version' => '0.0.1',
                    'label' => 'test',
                    'accessToken' => 'test',
                    'active' => true,
                    'integration' => [
                        'label' => 'test',
                        'accessKey' => 'test',
                        'secretAccessKey' => 'test',
                    ],
                    'aclRole' => [
                        'name' => 'SwagApp',
                    ],
                ],
            ],
            [
                'id' => $this->template2Id,
                'path' => 'storefront/base.html.twig',
                'active' => true,
                'template' => self::SECOND_TEMPLATE,
                'app' => [
                    'name' => 'StorefrontTheme',
                    'path' => __DIR__ . '/../../../Content/App/Manifest/_fixtures/test',
                    'version' => '0.0.1',
                    'label' => 'test',
                    'accessToken' => 'test',
                    'active' => false,
                    'integration' => [
                        'label' => 'test',
                        'accessKey' => 'test',
                        'secretAccessKey' => 'test',
                    ],
                    'aclRole' => [
                        'name' => 'SwagApp',
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }
}
