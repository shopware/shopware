<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\MySQLFactory;
use Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Twig\Error\LoaderError;

/**
 * @internal
 */
#[CoversClass(EntityTemplateLoader::class)]
class EntityTemplateLoaderTest extends TestCase
{
    use EnvTestBehaviour;

    public function testTemplatesAreOnlyLoadedOnce(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'path' => 'storefront/page/index.html.twig',
                    'template' => '<div>Hello</div>',
                    'hash' => 'abc123',
                    'updatedAt' => null,
                    'namespace' => 'TestApp',
                ],
            ]);

        $loader = new EntityTemplateLoader($connection, 'prod');

        static::assertTrue($loader->exists('@TestApp/storefront/page/index.html.twig'));
        static::assertTrue($loader->exists('@TestApp/storefront/page/index.html.twig'));
    }

    public function testResetClearsCache(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'path' => 'storefront/page/index.html.twig',
                    'template' => '<div>Hello</div>',
                    'hash' => 'abc123',
                    'updatedAt' => null,
                    'namespace' => 'TestApp',
                ],
            ]);

        $loader = new EntityTemplateLoader($connection, 'prod');

        static::assertTrue($loader->exists('@TestApp/storefront/page/index.html.twig'));

        $loader->reset();

        static::assertTrue($loader->exists('@TestApp/storefront/page/index.html.twig'));
    }

    public function testDevEnvironmentAlwaysReturnsNull(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())
            ->method('fetchAllAssociative');

        $loader = new EntityTemplateLoader($connection, 'dev');

        static::assertFalse($loader->exists('@TestApp/storefront/page/index.html.twig'));
    }

    public function testGetSourceContext(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'path' => 'storefront/page/index.html.twig',
                    'template' => '<div>Hello</div>',
                    'hash' => 'abc123',
                    'updatedAt' => null,
                    'namespace' => 'TestApp',
                ],
            ]);

        $loader = new EntityTemplateLoader($connection, 'prod');

        $source = $loader->getSourceContext('@TestApp/storefront/page/index.html.twig');
        static::assertSame('<div>Hello</div>', $source->getCode());
        static::assertSame('@TestApp/storefront/page/index.html.twig', $source->getName());
    }

    public function testGetSourceContextThrowsForMissingTemplate(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([]);

        $loader = new EntityTemplateLoader($connection, 'prod');

        $this->expectExceptionObject(new LoaderError('Template "@TestApp/storefront/page/missing.html.twig" is not defined.'));
        $loader->getSourceContext('@TestApp/storefront/page/missing.html.twig');
    }

    public function testGetCacheKey(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'path' => 'storefront/page/index.html.twig',
                    'template' => '<div>Hello</div>',
                    'hash' => 'abc123',
                    'updatedAt' => null,
                    'namespace' => 'TestApp',
                ],
            ]);

        $loader = new EntityTemplateLoader($connection, 'prod');

        $cacheKey = $loader->getCacheKey('@TestApp/storefront/page/index.html.twig');
        static::assertSame('@TestApp/storefront/page/index.html.twig_abc123', $cacheKey);
    }

    public function testIsFreshWithNoUpdatedAt(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'path' => 'storefront/page/index.html.twig',
                    'template' => '<div>Hello</div>',
                    'hash' => 'abc123',
                    'updatedAt' => null,
                    'namespace' => 'TestApp',
                ],
            ]);

        $loader = new EntityTemplateLoader($connection, 'prod');

        static::assertTrue($loader->isFresh('@TestApp/storefront/page/index.html.twig', time()));
    }

    public function testIsFreshWithOldUpdatedAt(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'path' => 'storefront/page/index.html.twig',
                    'template' => '<div>Hello</div>',
                    'hash' => 'abc123',
                    'updatedAt' => '2020-01-01 00:00:00',
                    'namespace' => 'TestApp',
                ],
            ]);

        $loader = new EntityTemplateLoader($connection, 'prod');

        static::assertTrue($loader->isFresh('@TestApp/storefront/page/index.html.twig', time()));
    }

    public function testIsFreshWithFutureUpdatedAt(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'path' => 'storefront/page/index.html.twig',
                    'template' => '<div>Hello</div>',
                    'hash' => 'abc123',
                    'updatedAt' => '2099-01-01 00:00:00',
                    'namespace' => 'TestApp',
                ],
            ]);

        $loader = new EntityTemplateLoader($connection, 'prod');

        static::assertFalse($loader->isFresh('@TestApp/storefront/page/index.html.twig', time()));
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            ['app_template.written' => 'reset'],
            EntityTemplateLoader::getSubscribedEvents()
        );
    }

    public function testTemplateWithoutNamespace(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'path' => 'storefront/page/index.html.twig',
                    'template' => '<div>Hello</div>',
                    'hash' => 'abc123',
                    'updatedAt' => null,
                    'namespace' => '',
                ],
            ]);

        $loader = new EntityTemplateLoader($connection, 'prod');

        static::assertTrue($loader->exists('storefront/page/index.html.twig'));
    }

    public function testNonExistentTemplateReturnsFalse(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([]);

        $loader = new EntityTemplateLoader($connection, 'prod');

        static::assertFalse($loader->exists('@TestApp/storefront/page/missing.html.twig'));
    }

    public function testDatabaselessModeReturnsFalse(): void
    {
        $this->setEnvVars(['DATABASE_URL' => MySQLFactory::PLACEHOLDER_DATABASE_URL]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');

        $loader = new EntityTemplateLoader($connection, 'prod');

        static::assertFalse($loader->exists('@TestApp/storefront/page/index.html.twig'));
    }
}
