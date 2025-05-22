<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Files;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\System\Snippet\Files\AppSnippetFileLoader;
use Shopware\Core\System\Snippet\Files\GenericSnippetFile;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\Files\SnippetFileLoader;
use Shopware\Tests\Unit\Core\System\Snippet\Files\_fixtures\BaseSnippetSet\BaseSnippetSet;
use Shopware\Tests\Unit\Core\System\Snippet\Files\_fixtures\ShopwareBundleWithSnippets\ShopwareBundleWithSnippets;
use Shopware\Tests\Unit\Core\System\Snippet\Files\_fixtures\SnippetSet\SnippetSet;

/**
 * @internal
 */
#[CoversClass(SnippetFileLoader::class)]
class SnippetFileLoaderTest extends TestCase
{
    public function testLoadSnippetsFromShopwareBundle(): void
    {
        $kernel = new MockedKernel(
            [
                'ShopwareBundleWithSnippets' => new ShopwareBundleWithSnippets(),
            ]
        );

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $this->createMock(Connection::class),
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            )
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('de-DE')[0];
        static::assertSame('storefront.de-DE', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.de-DE.json',
            $snippetFile->getPath()
        );
        static::assertSame('de-DE', $snippetFile->getIso());
        static::assertSame('Shopware', $snippetFile->getAuthor());
        static::assertFalse($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('en-GB')[0];
        static::assertSame('storefront.en-GB', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.en-GB.json',
            $snippetFile->getPath()
        );
        static::assertSame('en-GB', $snippetFile->getIso());
        static::assertSame('Shopware', $snippetFile->getAuthor());
        static::assertSame('ShopwareBundleWithSnippets', $snippetFile->getTechnicalName());
        static::assertFalse($snippetFile->isBase());
    }

    public function testLoadSnippetFilesIntoCollectionDoesNotOverwriteFiles(): void
    {
        $kernel = new MockedKernel(
            [
                'ShopwareBundleWithSnippets' => new ShopwareBundleWithSnippets(),
            ]
        );

        $collection = new SnippetFileCollection([
            new GenericSnippetFile(
                'test',
                __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.de-DE.json',
                'xx-XX',
                'test Author',
                true,
                'ShopwareBundleWithSnippets'
            ),
            new GenericSnippetFile(
                'test',
                __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.en-GB.json',
                'yy-YY',
                'test Author',
                true,
                'ShopwareBundleWithSnippets'
            ),
        ]);

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $this->createMock(Connection::class),
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            )
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('xx-XX')[0];
        static::assertSame('test', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.de-DE.json',
            $snippetFile->getPath()
        );
        static::assertSame('xx-XX', $snippetFile->getIso());
        static::assertSame('test Author', $snippetFile->getAuthor());
        static::assertTrue($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('yy-YY')[0];
        static::assertSame('test', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.en-GB.json',
            $snippetFile->getPath()
        );
        static::assertSame('yy-YY', $snippetFile->getIso());
        static::assertSame('test Author', $snippetFile->getAuthor());
        static::assertSame('ShopwareBundleWithSnippets', $snippetFile->getTechnicalName());
        static::assertTrue($snippetFile->isBase());
    }

    public function testLoadSnippetsFromPlugin(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllKeyValue')->willReturn([
            SnippetSet::class => 'Plugin Manufacturer',
        ]);

        $kernel = new MockedKernel(
            [
                'SnippetSet' => new SnippetSet(true, __DIR__),
            ]
        );

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $connection,
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            )
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('de-DE')[0];
        static::assertSame('storefront.de-DE', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/SnippetSet/Resources/snippet/storefront.de-DE.json',
            $snippetFile->getPath()
        );
        static::assertSame('de-DE', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertFalse($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('en-GB')[0];
        static::assertSame('storefront.en-GB', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/SnippetSet/Resources/snippet/storefront.en-GB.json',
            $snippetFile->getPath()
        );
        static::assertSame('en-GB', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertSame('SnippetSet', $snippetFile->getTechnicalName());
        static::assertFalse($snippetFile->isBase());
    }

    public function testLoadBaseSnippetsFromPlugin(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllKeyValue')->willReturn([
            BaseSnippetSet::class => 'Plugin Manufacturer',
        ]);

        $kernel = new MockedKernel(
            [
                'BaseSnippetSet' => new BaseSnippetSet(true, __DIR__),
            ]
        );

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $connection,
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            )
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(4, $collection);
        static::assertCount(2, $collection->getSnippetFilesByIso('de-DE'));

        $snippetFile = $collection->getByName('de-DE');
        static::assertInstanceOf(GenericSnippetFile::class, $snippetFile);
        static::assertSame('de-DE', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/BaseSnippetSet/Resources/app/administration/src/module/sw-module/snippet/de-DE.json',
            $snippetFile->getPath()
        );
        static::assertSame('de-DE', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertSame('BaseSnippetSet', $snippetFile->getTechnicalName());
        static::assertFalse($snippetFile->isBase());

        $snippetFile = $collection->getByName('storefront.de-DE');
        static::assertInstanceOf(GenericSnippetFile::class, $snippetFile);
        static::assertSame('storefront.de-DE', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/BaseSnippetSet/Resources/snippet/storefront.de-DE.base.json',
            $snippetFile->getPath()
        );
        static::assertSame('de-DE', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertSame('BaseSnippetSet', $snippetFile->getTechnicalName());
        static::assertTrue($snippetFile->isBase());

        static::assertCount(2, $collection->getSnippetFilesByIso('en-GB'));

        $snippetFile = $collection->getByName('en-GB');
        static::assertInstanceOf(GenericSnippetFile::class, $snippetFile);
        static::assertSame('en-GB', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/BaseSnippetSet/Resources/app/administration/src/module/sw-module/snippet/en-GB.json',
            $snippetFile->getPath()
        );
        static::assertSame('en-GB', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertSame('BaseSnippetSet', $snippetFile->getTechnicalName());
        static::assertFalse($snippetFile->isBase());

        $snippetFile = $collection->getByName('storefront.en-GB');
        static::assertInstanceOf(GenericSnippetFile::class, $snippetFile);
        static::assertSame('storefront.en-GB', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/BaseSnippetSet/Resources/snippet/storefront.en-GB.base.json',
            $snippetFile->getPath()
        );
        static::assertSame('en-GB', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertTrue($snippetFile->isBase());
    }
}
