<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader;
use Twig\Error\LoaderError;
use Twig\Source;

/**
 * @internal
 */
#[CoversClass(EntityTemplateLoader::class)]
class EntityTemplateLoaderTest extends TestCase
{
    /**
     * @var Connection&MockObject
     */
    private Connection $connectionMock;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);
    }

    public function testSubscribedEvents(): void
    {
        $subscribedEvents = EntityTemplateLoader::getSubscribedEvents();

        static::assertEquals(['app_template.written' => 'reset'], $subscribedEvents);
    }

    public function testDevMode(): void
    {
        $entityTemplateLoader = new EntityTemplateLoader($this->connectionMock, 'dev');

        $this->connectionMock->expects(static::never())->method('fetchAllAssociative');

        $result = $entityTemplateLoader->exists('@test/test');

        static::assertFalse($result);

        $result = $entityTemplateLoader->isFresh('@test/test', \time());

        static::assertFalse($result);

        static::expectException(LoaderError::class);
        static::expectExceptionMessage(sprintf('Template "%s" is not defined.', 'test'));

        $entityTemplateLoader->getSourceContext('test');
    }

    public function testDisabledExtensionMode(): void
    {
        try {
            $_ENV['DISABLE_EXTENSIONS'] = true;

            $entityTemplateLoader = new EntityTemplateLoader($this->connectionMock, 'prod');

            $this->connectionMock->expects(static::never())->method('fetchAllAssociative');

            $result = $entityTemplateLoader->exists('@test/test');

            static::assertFalse($result);

            $result = $entityTemplateLoader->isFresh('@test/test', \time());

            static::assertFalse($result);

            static::expectException(LoaderError::class);
            static::expectExceptionMessage(sprintf('Template "%s" is not defined.', '@test/test'));

            $entityTemplateLoader->getSourceContext('@test/test');
        } finally {
            $_ENV['DISABLE_EXTENSIONS'] = false;
        }
    }

    public function testProdModeNoResult(): void
    {
        $entityTemplateLoader = new EntityTemplateLoader($this->connectionMock, 'prod');

        $this->connectionMock->expects(static::once())->method('fetchAllAssociative')->willReturn([]);

        $result = $entityTemplateLoader->exists('@test/test');

        static::assertFalse($result);

        $result = $entityTemplateLoader->isFresh('@test/test', \time());

        static::assertFalse($result);

        static::expectException(LoaderError::class);
        static::expectExceptionMessage(sprintf('Template "%s" is not defined.', '@test/test'));

        $entityTemplateLoader->getSourceContext('@test/test');
    }

    public function testProdModeMissingNamespace(): void
    {
        $entityTemplateLoader = new EntityTemplateLoader($this->connectionMock, 'prod');

        $this->connectionMock->expects(static::once())->method('fetchAllAssociative')->willReturn(
            [
                [
                    'template' => '<html></html>',
                    'path' => 'test',
                    'namespace' => 'test',
                    'updatedAt' => '2000-01-01',
                ],
            ]
        );

        $result = $entityTemplateLoader->exists('test');

        static::assertFalse($result);

        $result = $entityTemplateLoader->isFresh('test', \time());

        static::assertFalse($result);

        static::expectException(LoaderError::class);
        static::expectExceptionMessage(sprintf('Template "%s" is not defined.', 'test'));

        $entityTemplateLoader->getSourceContext('test');
    }

    public function testProdModeWithResult(): void
    {
        $entityTemplateLoader = new EntityTemplateLoader($this->connectionMock, 'prod');

        $this->connectionMock->expects(static::once())->method('fetchAllAssociative')->willReturn(
            [
                [
                    'template' => '<html></html>',
                    'path' => 'test',
                    'namespace' => 'test',
                    'updatedAt' => '2000-01-01',
                ],
            ]
        );

        $result = $entityTemplateLoader->exists('@test/test');

        static::assertTrue($result);

        $result = $entityTemplateLoader->isFresh('@test/test', \time());

        static::assertTrue($result);

        $result = $entityTemplateLoader->getSourceContext('@test/test');

        static::assertEquals(new Source('<html></html>', '@test/test'), $result);
    }

    public function testProdModeReset(): void
    {
        $entityTemplateLoader = new EntityTemplateLoader($this->connectionMock, 'prod');

        $this->connectionMock->expects(static::exactly(2))->method('fetchAllAssociative')->willReturn(
            [
                [
                    'template' => '<html></html>',
                    'path' => 'test',
                    'namespace' => 'test',
                    'updatedAt' => '2000-01-01',
                ],
            ]
        );

        $result = $entityTemplateLoader->getSourceContext('@test/test');

        static::assertEquals(new Source('<html></html>', '@test/test'), $result);

        $entityTemplateLoader->reset();

        $result = $entityTemplateLoader->getSourceContext('@test/test');

        static::assertEquals(new Source('<html></html>', '@test/test'), $result);
    }
}
