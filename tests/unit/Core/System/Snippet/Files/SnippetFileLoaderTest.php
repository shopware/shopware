<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Files;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\System\Snippet\Files\AppSnippetFileLoader;
use Shopware\Core\System\Snippet\Files\GenericSnippetFile;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\Files\SnippetFileLoader;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\Struct\Language;
use Shopware\Core\System\Snippet\Struct\LanguageCollection;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Shopware\Tests\Unit\Administration\Snippet\SnippetFileTrait;
use Shopware\Tests\Unit\Core\System\Snippet\Files\_fixtures\BaseSnippetSet\BaseSnippetSet;
use Shopware\Tests\Unit\Core\System\Snippet\Files\_fixtures\ShopwareBundleWithSnippets\ShopwareBundleWithSnippets;
use Shopware\Tests\Unit\Core\System\Snippet\Files\_fixtures\SnippetSet\SnippetSet;
use Shopware\Tests\Unit\Core\System\Snippet\Mock\TestPlugin;

/**
 * @internal
 */
#[CoversClass(SnippetFileLoader::class)]
class SnippetFileLoaderTest extends TestCase
{
    use SnippetFileTrait;

    private TranslationConfig $config;

    protected function setUp(): void
    {
        $this->config = new TranslationConfig(
            'https://example.com',
            ['es-ES'],
            ['activePlugin'],
            new LanguageCollection([new Language('es-ES', 'Español')]),
            []
        );
    }

    protected function tearDown(): void
    {
        $this->cleanupSnippetFiles();
    }

    public function testLoadSnippetsFromShopwareBundle(): void
    {
        $kernel = $this->getKernel([
            'ShopwareBundleWithSnippets' => new ShopwareBundleWithSnippets(),
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $this->createMock(Connection::class),
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            ),
            $this->config
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
        $kernel = $this->getKernel([
            'ShopwareBundleWithSnippets' => new ShopwareBundleWithSnippets(),
        ]);

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
            ),
            $this->config
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

        $kernel = $this->getKernel([
            'SnippetSet' => new SnippetSet(true, __DIR__),
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $connection,
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            ),
            $this->config
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

        $kernel = $this->getKernel([
            'BaseSnippetSet' => new BaseSnippetSet(true, __DIR__),
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $connection,
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            ),
            $this->config
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

    public function testLoadInstalledCoreAndPluginSnippets(): void
    {
        $this->createSnippetFiles();

        $path = __DIR__ . '/_fixtures/activePlugin';

        $plugin = new TestPlugin(true, $path);
        $plugin->setName('activePlugin');
        $plugin->setPath($path);

        $kernel = $this->getKernel([], $plugin);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $this->createMock(Connection::class),
            $this->createMock(AppSnippetFileLoader::class),
            new ActiveAppsLoader(
                $this->createMock(Connection::class),
                $this->createMock(AppLoader::class),
                '/'
            ),
            $this->config
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);
        static::assertCount(6, $collection);

        $files = $collection->getElements();
        static::assertContainsOnlyInstancesOf(GenericSnippetFile::class, $files);

        $actualPaths = [];
        foreach ($files as $file) {
            $actualPaths[] = $file->getPath();
        }

        $expectedPaths = [
            TranslationLoader::TRANSLATION_DESTINATION . '/es-ES/Plugins/activePlugin/storefront.json',
            TranslationLoader::TRANSLATION_DESTINATION . '/es-ES/Plugins/activePlugin/messages.es-ES.base.json',
            TranslationLoader::TRANSLATION_DESTINATION . '/es-ES/Plugins/activePlugin/administration.json',
            TranslationLoader::TRANSLATION_DESTINATION . '/es-ES/Platform/storefront.json',
            TranslationLoader::TRANSLATION_DESTINATION . '/es-ES/Platform/messages.es-ES.base.json',
            TranslationLoader::TRANSLATION_DESTINATION . '/es-ES/Platform/administration.json',
        ];

        foreach ($actualPaths as $path) {
            static::assertContains($path, $expectedPaths);
        }
    }

    /**
     * @param array<string, Bundle> $bundles
     */
    private function getKernel(array $bundles, ?Plugin $plugin = null): MockedKernel
    {
        $pluginCollection = new KernelPluginCollection();

        if ($plugin) {
            $pluginCollection->add($plugin);
        }

        $pluginLoader = $this->createMock(KernelPluginLoader::class);
        $pluginLoader->method('getPluginInstances')->willReturn($pluginCollection);

        return new MockedKernel($bundles, $pluginLoader);
    }
}
