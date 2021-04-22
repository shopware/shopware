<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Snippet\Files;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Snippet\Files\AppSnippetFileLoader;
use Shopware\Core\System\Snippet\Files\GenericSnippetFile;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\Files\SnippetFileLoader;
use Shopware\Core\System\Test\Snippet\Files\_fixtures\BaseSnippetSet\BaseSnippetSet;
use Shopware\Core\System\Test\Snippet\Files\_fixtures\ShopwareBundleWithSnippets\ShopwareBundleWithSnippets;
use Shopware\Core\System\Test\Snippet\Files\_fixtures\SnippetSet\SnippetSet;

class SnippetFileLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testLoadSnippetsFromShopwareBundle(): void
    {
        $kernel = new MockedKernel(
            [
                new ShopwareBundleWithSnippets(),
            ]
        );

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(AppSnippetFileLoader::class),
            $this->getContainer()->get(ActiveAppsLoader::class)
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('de-DE')[0];
        static::assertEquals('storefront.de-DE', $snippetFile->getName());
        static::assertEquals(
            __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.de-DE.json',
            $snippetFile->getPath()
        );
        static::assertEquals('de-DE', $snippetFile->getIso());
        static::assertEquals('Shopware', $snippetFile->getAuthor());
        static::assertFalse($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('en-GB')[0];
        static::assertEquals('storefront.en-GB', $snippetFile->getName());
        static::assertEquals(
            __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.en-GB.json',
            $snippetFile->getPath()
        );
        static::assertEquals('en-GB', $snippetFile->getIso());
        static::assertEquals('Shopware', $snippetFile->getAuthor());
        static::assertFalse($snippetFile->isBase());
    }

    public function testLoadSnippetFilesIntoCollectionDoesNotOverwriteFiles(): void
    {
        $kernel = new MockedKernel(
            [
                new ShopwareBundleWithSnippets(),
            ]
        );

        $collection = new SnippetFileCollection([
            new GenericSnippetFile(
                'test',
                __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.de-DE.json',
                'xx-XX',
                'test Author',
                true
            ),
            new GenericSnippetFile(
                'test',
                __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.en-GB.json',
                'yy-YY',
                'test Author',
                true
            ),
        ]);

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(AppSnippetFileLoader::class),
            $this->getContainer()->get(ActiveAppsLoader::class)
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('xx-XX')[0];
        static::assertEquals('test', $snippetFile->getName());
        static::assertEquals(
            __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.de-DE.json',
            $snippetFile->getPath()
        );
        static::assertEquals('xx-XX', $snippetFile->getIso());
        static::assertEquals('test Author', $snippetFile->getAuthor());
        static::assertTrue($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('yy-YY')[0];
        static::assertEquals('test', $snippetFile->getName());
        static::assertEquals(
            __DIR__ . '/_fixtures/ShopwareBundleWithSnippets/Resources/snippet/storefront.en-GB.json',
            $snippetFile->getPath()
        );
        static::assertEquals('yy-YY', $snippetFile->getIso());
        static::assertEquals('test Author', $snippetFile->getAuthor());
        static::assertTrue($snippetFile->isBase());
    }

    public function testLoadSnippetsFromPlugin(): void
    {
        /** @var EntityRepositoryInterface $pluginRepo */
        $pluginRepo = $this->getContainer()->get('plugin.repository');
        $pluginRepo->create([
            [
                'name' => 'SnippetSet',
                'label' => 'SnippetSet Plugin',
                'baseClass' => SnippetSet::class,
                'active' => true,
                'managedByComposer' => true,
                'autoload' => [],
                'author' => 'Plugin Manufacturer',
                'version' => '1.0.0',
            ],
        ], Context::createDefaultContext());

        $kernel = new MockedKernel(
            [
                new SnippetSet(true, __DIR__),
            ]
        );

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(AppSnippetFileLoader::class),
            $this->getContainer()->get(ActiveAppsLoader::class)
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('de-DE')[0];
        static::assertEquals('storefront.de-DE', $snippetFile->getName());
        static::assertEquals(
            __DIR__ . '/_fixtures/SnippetSet/Resources/snippet/storefront.de-DE.json',
            $snippetFile->getPath()
        );
        static::assertEquals('de-DE', $snippetFile->getIso());
        static::assertEquals('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertFalse($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('en-GB')[0];
        static::assertEquals('storefront.en-GB', $snippetFile->getName());
        static::assertEquals(
            __DIR__ . '/_fixtures/SnippetSet/Resources/snippet/storefront.en-GB.json',
            $snippetFile->getPath()
        );
        static::assertEquals('en-GB', $snippetFile->getIso());
        static::assertEquals('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertFalse($snippetFile->isBase());
    }

    public function testLoadBaseSnippetsFromPlugin(): void
    {
        /** @var EntityRepositoryInterface $pluginRepo */
        $pluginRepo = $this->getContainer()->get('plugin.repository');
        $pluginRepo->create([
            [
                'name' => 'BaseSnippetSet',
                'label' => 'BaseSnippetSet Plugin',
                'baseClass' => BaseSnippetSet::class,
                'active' => true,
                'managedByComposer' => true,
                'autoload' => [],
                'author' => 'Plugin Manufacturer',
                'version' => '1.0.0',
            ],
        ], Context::createDefaultContext());

        $kernel = new MockedKernel(
            [
                new BaseSnippetSet(true, __DIR__),
            ]
        );

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(AppSnippetFileLoader::class),
            $this->getContainer()->get(ActiveAppsLoader::class)
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('de-DE')[0];
        static::assertEquals('storefront.de-DE', $snippetFile->getName());
        static::assertEquals(
            __DIR__ . '/_fixtures/BaseSnippetSet/Resources/snippet/storefront.de-DE.base.json',
            $snippetFile->getPath()
        );
        static::assertEquals('de-DE', $snippetFile->getIso());
        static::assertEquals('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertTrue($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('en-GB')[0];
        static::assertEquals('storefront.en-GB', $snippetFile->getName());
        static::assertEquals(
            __DIR__ . '/_fixtures/BaseSnippetSet/Resources/snippet/storefront.en-GB.base.json',
            $snippetFile->getPath()
        );
        static::assertEquals('en-GB', $snippetFile->getIso());
        static::assertEquals('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertTrue($snippetFile->isBase());
    }
}
