<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Filesystem\MemoryFilesystemAdapter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Theme\SeedingThemePathBuilder;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File as StorefrontPluginConfigurationFile;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeFileImporter;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeScripts;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\MockStorefront\MockStorefront;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeAndPlugin\AsyncPlugin\AsyncPlugin;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeAndPlugin\NotFoundPlugin\NotFoundPlugin;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeAndPlugin\TestTheme\TestTheme;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeFixtures;

/**
 * @internal
 */
#[CoversClass(ThemeScripts::class)]
class ThemeScriptsTest extends TestCase
{
    public function testGetThemeScriptsWithThemeIdNull(): void
    {
        $salesChannelContext = Generator::createSalesChannelContext();

        $themeScripts = new ThemeScripts(
            $this->createMock(StorefrontPluginRegistry::class),
            $this->createMock(ThemeFileResolver::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(SeedingThemePathBuilder::class),
            $this->createMock(FilesystemOperator::class),
            $this->createMock(ThemeFileImporter::class),
            $this->createMock(LoggerInterface::class),
        );

        static::assertEquals([], $themeScripts->getThemeScripts($salesChannelContext, null));
    }

    public function testGetThemeScriptsReturnsAtLeastOneFile(): void
    {
        $salesChannelContext = Generator::createSalesChannelContext();

        $themeId = '018c7c7cfc0f7ab9a30e557310cdbed9';
        $parentThemeId = '018c39effbf273e79ea1a7430854f12f';

        $searchResult = new EntitySearchResult(
            'theme',
            3,
            $this->getThemesCollectionStorefront($themeId, $parentThemeId),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $themeRepositoryMock = $this->createMock(EntityRepository::class);
        $themeRepositoryMock->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $configCollection = $this->getConfigCollection();
        $storefrontPluginRegistryMock = $this->createMock(StorefrontPluginRegistry::class);
        $storefrontPluginRegistryMock->expects(static::exactly(2))
            ->method('getConfigurations')
            ->willReturn($configCollection);

        // Hint: to get Symfony Finder to work correctly, you need to use the absolute path
        $absolutePath = __DIR__;
        $location = $absolutePath . '/fixtures/ThemeAndPlugin/TestTheme';
        $seedingThemePathBuilderMock = $this->createMock(SeedingThemePathBuilder::class);
        $seedingThemePathBuilderMock->expects(static::once())
            ->method('assemblePath')
            ->willReturn($location);

        $themeFileSystem = new Filesystem(new MemoryFilesystemAdapter());
        $themeFileSystem->createDirectory($location);
        $themeFileSystem->createDirectory($location . '/js');
        $themeFileSystem->createDirectory($location . '/js/test-theme');
        $themeFileSystem->write($location . '/js/test-theme/test-theme.js', 'console.log("test");');

        $jsFileCollection = new FileCollection();
        $jsFileCollection->add(new StorefrontPluginConfigurationFile($location . '/test-theme.js'));

        $themeFileResolverMock = $this->createMock(ThemeFileResolver::class);
        $themeFileResolverMock->expects(static::once())
            ->method('resolveFiles')
            ->willReturn([ThemeFileResolver::SCRIPT_FILES => $jsFileCollection, ThemeFileResolver::STYLE_FILES => new FileCollection()]);

        $themeFileImporterMock = $this->createMock(ThemeFileImporter::class);
        $themeFileImporterMock->expects(static::once())
            ->method('getRealPath')
            ->willReturn($location);

        $themeScripts = new ThemeScripts(
            $storefrontPluginRegistryMock,
            $themeFileResolverMock,
            $themeRepositoryMock,
            $seedingThemePathBuilderMock,
            $themeFileSystem,
            $themeFileImporterMock,
            $this->createMock(LoggerInterface::class),
        );

        $result = $themeScripts->getThemeScripts($salesChannelContext, $themeId);

        static::assertArrayHasKey(0, $result);
    }

    public function testGetThemeScriptsReturnsAtLeastOneFileWhenTechnicalNameIsNull(): void
    {
        $salesChannelContext = Generator::createSalesChannelContext();

        $themeId = '018c7c7cfc0f7ab9a30e557310cdbed9';
        $parentThemeId = '018c39effbf273e79ea1a7430854f12f';

        $searchResult = new EntitySearchResult(
            'theme',
            3,
            $this->getThemesCollectionNullAndMockStorefront($themeId, $parentThemeId),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $themeRepositoryMock = $this->createMock(EntityRepository::class);
        $themeRepositoryMock->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $configCollection = $this->getConfigCollectionWithMockStorefront();
        $storefrontPluginRegistryMock = $this->createMock(StorefrontPluginRegistry::class);
        $storefrontPluginRegistryMock->expects(static::exactly(2))
            ->method('getConfigurations')
            ->willReturn($configCollection);

        // Hint: to get Symfony Finder to work correctly, you need to use the absolute path
        $absolutePath = __DIR__;
        $location = $absolutePath . '/fixtures/ThemeAndPlugin/TestTheme';
        $seedingThemePathBuilderMock = $this->createMock(SeedingThemePathBuilder::class);
        $seedingThemePathBuilderMock->expects(static::once())
            ->method('assemblePath')
            ->willReturn($location);

        $themeFileSystem = new Filesystem(new MemoryFilesystemAdapter());
        $themeFileSystem->createDirectory($location);
        $themeFileSystem->createDirectory($location . '/js');
        $themeFileSystem->createDirectory($location . '/js/test-theme');
        $themeFileSystem->write($location . '/js/test-theme/test-theme.js', 'console.log("test");');

        $jsFileCollection = new FileCollection();
        $jsFileCollection->add(new StorefrontPluginConfigurationFile($location . '/test-theme.js'));

        $themeFileResolverMock = $this->createMock(ThemeFileResolver::class);
        $themeFileResolverMock->expects(static::once())
            ->method('resolveFiles')
            ->willReturn([ThemeFileResolver::SCRIPT_FILES => $jsFileCollection, ThemeFileResolver::STYLE_FILES => new FileCollection()]);

        $themeFileImporterMock = $this->createMock(ThemeFileImporter::class);
        $themeFileImporterMock->expects(static::once())
            ->method('getRealPath')
            ->willReturn($location);

        $themeScripts = new ThemeScripts(
            $storefrontPluginRegistryMock,
            $themeFileResolverMock,
            $themeRepositoryMock,
            $seedingThemePathBuilderMock,
            $themeFileSystem,
            $themeFileImporterMock,
            $this->createMock(LoggerInterface::class)
        );

        $result = $themeScripts->getThemeScripts($salesChannelContext, $themeId);

        static::assertArrayHasKey(0, $result);
    }

    public function testGetThemeScriptsLoggerIsExecuted(): void
    {
        $salesChannelContext = Generator::createSalesChannelContext();

        $themeId = '018c7c7cfc0f7ab9a30e557310cdbed9';
        $parentThemeId = '018c39effbf273e79ea1a7430854f12f';

        $searchResult = new EntitySearchResult(
            'theme',
            3,
            $this->getThemesCollectionNullAndMockStorefront($themeId, $parentThemeId),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $themeRepositoryMock = $this->createMock(EntityRepository::class);
        $themeRepositoryMock->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $configCollection = $this->getConfigCollectionWithMockStorefront();
        $storefrontPluginRegistryMock = $this->createMock(StorefrontPluginRegistry::class);
        $storefrontPluginRegistryMock->expects(static::once())
            ->method('getConfigurations')
            ->willReturn($configCollection);

        $location = '/fixtures/ThemeAndPlugin/TestTheme';
        $seedingThemePathBuilderMock = $this->createMock(SeedingThemePathBuilder::class);
        $seedingThemePathBuilderMock->expects(static::once())
            ->method('assemblePath')
            ->willReturn($location);

        $themeFileSystem = new Filesystem(new MemoryFilesystemAdapter());
        $themeFileSystem->createDirectory($location);
        $themeFileSystem->createDirectory($location . '/js');
        $themeFileSystem->createDirectory($location . '/js/test-theme');
        $themeFileSystem->write($location . '/js/test-theme/test-theme.js', 'console.log("test");');

        $themeFileResolverMock = $this->createMock(ThemeFileResolver::class);

        $themeFileImporterMock = $this->createMock(ThemeFileImporter::class);
        $themeFileImporterMock->expects(static::once())
            ->method('getRealPath')
            ->willReturn($location);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects(static::once())
            ->method('error');

        $themeScripts = new ThemeScripts(
            $storefrontPluginRegistryMock,
            $themeFileResolverMock,
            $themeRepositoryMock,
            $seedingThemePathBuilderMock,
            $themeFileSystem,
            $themeFileImporterMock,
            $loggerMock
        );

        $themeScripts->getThemeScripts($salesChannelContext, $themeId);
    }

    private function getThemesCollectionStorefront(string $themeId, string $parentThemeId): ThemeCollection
    {
        return new ThemeCollection([
            (new ThemeEntity())->assign(
                [
                    'id' => $themeId,
                    '_uniqueIdentifier' => $themeId,
                    'salesChannels' => new SalesChannelCollection(),
                    'technicalName' => 'TestTheme',
                    'parentThemeId' => $parentThemeId,
                    'labels' => [
                        'testlabel',
                    ],
                    'helpTexts' => [
                        'testHelp',
                    ],
                    'baseConfig' => [
                        'configInheritance' => [
                            '@ParentTheme',
                        ],
                        'config' => ThemeFixtures::getThemeJsonConfig(),
                    ],
                    'configValues' => [
                        'test' => ['value' => ['no_test']],
                    ],
                ]
            ),
            (new ThemeEntity())->assign(
                [
                    'id' => $parentThemeId,
                    'technicalName' => StorefrontPluginRegistry::BASE_THEME_NAME,
                    '_uniqueIdentifier' => $parentThemeId,
                ]
            ),
        ]);
    }

    private function getConfigCollection(): StorefrontPluginConfigurationCollection
    {
        $projectDir = 'tests/unit/Storefront/Theme/fixtures';
        $configurationFactory = new StorefrontPluginConfigurationFactory($projectDir);
        $themePluginBundle = new TestTheme();
        $asyncPluginBundle = new AsyncPlugin(true, $projectDir . 'fixtures/ThemeAndPlugin/AsyncPlugin');
        $notFoundPluginBundle = new NotFoundPlugin(
            true,
            $projectDir . 'fixtures/ThemeAndPlugin/NotFoundPlugin'
        );
        $testTheme = $configurationFactory->createFromBundle($themePluginBundle);
        $asyncPlugin = $configurationFactory->createFromBundle($asyncPluginBundle);

        $notFoundPlugin = $configurationFactory->createFromBundle($notFoundPluginBundle);
        $scripts = new FileCollection();
        $scripts = $scripts::createFromArray([
            $projectDir . 'fixtures/ThemeAndPlugin/NotFoundPlugin/src/Resources/app/storefront/src/plugins/lorem-ipsum/plugin.js',
        ]);
        $notFoundPlugin->setScriptFiles($scripts);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($testTheme);
        $configCollection->add($asyncPlugin);
        $configCollection->add($notFoundPlugin);

        return $configCollection;
    }

    private function getThemesCollectionNullAndMockStorefront(string $themeId, string $parentThemeId): ThemeCollection
    {
        return new ThemeCollection([
            (new ThemeEntity())->assign(
                [
                    'id' => $themeId,
                    '_uniqueIdentifier' => $themeId,
                    'salesChannels' => new SalesChannelCollection(),
                    'technicalName' => null,
                    'parentThemeId' => $parentThemeId,
                    'labels' => [
                        'testlabel',
                    ],
                    'helpTexts' => [
                        'testHelp',
                    ],
                    'baseConfig' => [
                        'configInheritance' => [
                            '@ParentTheme',
                        ],
                        'config' => ThemeFixtures::getThemeJsonConfig(),
                    ],
                    'configValues' => [
                        'test' => ['value' => ['no_test']],
                    ],
                ]
            ),
            (new ThemeEntity())->assign(
                [
                    'id' => $parentThemeId,
                    'technicalName' => 'MockStorefront',
                    '_uniqueIdentifier' => $parentThemeId,
                ]
            ),
        ]);
    }

    private function getConfigCollectionWithMockStorefront(): StorefrontPluginConfigurationCollection
    {
        $projectDir = 'tests/unit/Storefront/Theme/fixtures';
        $configurationFactory = new StorefrontPluginConfigurationFactory($projectDir);
        $storefrontBundle = new MockStorefront();
        $themePluginBundle = new TestTheme();
        $asyncPluginBundle = new AsyncPlugin(true, $projectDir . 'fixtures/ThemeAndPlugin/AsyncPlugin');
        $notFoundPluginBundle = new NotFoundPlugin(
            true,
            $projectDir . 'fixtures/ThemeAndPlugin/NotFoundPlugin'
        );
        $storefront = $configurationFactory->createFromBundle($storefrontBundle);
        $testTheme = $configurationFactory->createFromBundle($themePluginBundle);
        $asyncPlugin = $configurationFactory->createFromBundle($asyncPluginBundle);

        $notFoundPlugin = $configurationFactory->createFromBundle($notFoundPluginBundle);
        $scripts = new FileCollection();
        $scripts = $scripts::createFromArray([
            $projectDir . 'fixtures/ThemeAndPlugin/NotFoundPlugin/src/Resources/app/storefront/src/plugins/lorem-ipsum/plugin.js',
        ]);
        $notFoundPlugin->setScriptFiles($scripts);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($storefront);
        $configCollection->add($testTheme);
        $configCollection->add($asyncPlugin);
        $configCollection->add($notFoundPlugin);

        return $configCollection;
    }
}
