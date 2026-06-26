<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeDefinition;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ThemeLifecycleService::class)]
class ThemeLifecycleServiceTest extends TestCase
{
    public function testRefreshThemeLogsAndSkipsMediaImportFailuresOutsideDev(): void
    {
        $context = Context::createDefaultContext();
        $configuration = $this->createThemeConfiguration();
        $assetRoot = $this->createAssetRoot();
        $exception = MediaException::invalidFile('Broken media');

        $fileSaver = $this->createMock(FileSaver::class);
        $fileSaver->expects($this->once())->method('persistFileToMedia')->willThrowException($exception);

        $failedMediaId = null;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            'Could not import theme media file.',
            static::callback(static function (array $logContext) use (&$failedMediaId, $configuration, $exception): bool {
                $failedMediaId = $logContext['mediaId'] ?? null;

                return $logContext['theme'] === $configuration->getTechnicalName()
                    && $logContext['path'] === 'app/storefront/src/assets/image/shopware_logo.svg'
                    && $failedMediaId !== null
                    && $logContext['exception'] === $exception;
            })
        );

        $runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfigService->expects($this->once())->method('refreshRuntimeConfig');
        $runtimeConfigService->expects($this->once())->method('resetCaches');

        $repositories = $this->createRepositories();
        $service = $this->createService($configuration, $assetRoot, $fileSaver, $logger, $runtimeConfigService, 'test', $repositories);

        try {
            $service->refreshTheme($configuration, $context);
        } finally {
            $this->removeDirectory($assetRoot);
        }

        static::assertIsString($failedMediaId);
        static::assertSame($failedMediaId, $repositories['media']->creates[0][0]['id']);
        static::assertNull($repositories['media']->creates[0][0]['mediaFolderId']);
        static::assertSame([['id' => $failedMediaId]], $repositories['media']->deletes[0]);

        $themePayload = $repositories['theme']->upserts[0][0];
        static::assertSame([], $themePayload['media']);
        static::assertNull($themePayload['baseConfig']['fields']['brokenMedia']['value']);
    }

    public function testRefreshThemeRethrowsMediaImportFailuresInDev(): void
    {
        $context = Context::createDefaultContext();
        $configuration = $this->createThemeConfiguration();
        $assetRoot = $this->createAssetRoot();
        $exception = MediaException::invalidFile('Broken media');

        $fileSaver = $this->createMock(FileSaver::class);
        $fileSaver->expects($this->once())->method('persistFileToMedia')->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfigService->expects($this->never())->method('refreshRuntimeConfig');
        $runtimeConfigService->expects($this->never())->method('resetCaches');

        $repositories = $this->createRepositories();
        $service = $this->createService($configuration, $assetRoot, $fileSaver, $logger, $runtimeConfigService, 'dev', $repositories);

        $this->expectExceptionObject($exception);

        try {
            $service->refreshTheme($configuration, $context);
        } finally {
            $this->removeDirectory($assetRoot);
        }
    }

    private function createThemeConfiguration(): StorefrontPluginConfiguration
    {
        $configuration = new StorefrontPluginConfiguration('TestTheme');
        $configuration->setName('TestTheme');
        $configuration->setAuthor('shopware AG');
        $configuration->setIsTheme(true);
        $configuration->setThemeJson([]);
        $configuration->setThemeConfig([
            'fields' => [
                'brokenMedia' => [
                    'type' => 'media',
                    'value' => 'app/storefront/src/assets/image/shopware_logo.svg',
                ],
            ],
        ]);

        return $configuration;
    }

    /**
     * @return array{
     *     theme: StaticEntityRepository<ThemeCollection>,
     *     media: StaticEntityRepository<MediaCollection>,
     *     mediaFolder: StaticEntityRepository<MediaFolderCollection>,
     *     themeMedia: StaticEntityRepository<EntityCollection<Entity>>,
     *     language: StaticEntityRepository<LanguageCollection>,
     *     themeChild: StaticEntityRepository<EntityCollection<Entity>>
     * }
     */
    private function createRepositories(): array
    {
        /** @var StaticEntityRepository<ThemeCollection> $themeRepository */
        $themeRepository = new StaticEntityRepository([new ThemeCollection()], new ThemeDefinition());
        /** @var StaticEntityRepository<MediaCollection> $mediaRepository */
        $mediaRepository = new StaticEntityRepository([[]], new MediaDefinition());
        /** @var StaticEntityRepository<MediaFolderCollection> $mediaFolderRepository */
        $mediaFolderRepository = new StaticEntityRepository([[]]);
        /** @var StaticEntityRepository<EntityCollection<Entity>> $themeMediaRepository */
        $themeMediaRepository = new StaticEntityRepository([]);
        /** @var StaticEntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new StaticEntityRepository([new LanguageCollection([$this->createSystemLanguage()])]);
        /** @var StaticEntityRepository<EntityCollection<Entity>> $themeChildRepository */
        $themeChildRepository = new StaticEntityRepository([[]]);

        return [
            'theme' => $themeRepository,
            'media' => $mediaRepository,
            'mediaFolder' => $mediaFolderRepository,
            'themeMedia' => $themeMediaRepository,
            'language' => $languageRepository,
            'themeChild' => $themeChildRepository,
        ];
    }

    /**
     * @param array{
     *     theme: StaticEntityRepository<ThemeCollection>,
     *     media: StaticEntityRepository<MediaCollection>,
     *     mediaFolder: StaticEntityRepository<MediaFolderCollection>,
     *     themeMedia: StaticEntityRepository<EntityCollection<Entity>>,
     *     language: StaticEntityRepository<LanguageCollection>,
     *     themeChild: StaticEntityRepository<EntityCollection<Entity>>
     * } $repositories
     */
    private function createService(
        StorefrontPluginConfiguration $configuration,
        string $assetRoot,
        FileSaver $fileSaver,
        LoggerInterface $logger,
        ThemeRuntimeConfigService $runtimeConfigService,
        string $environment,
        array $repositories
    ): ThemeLifecycleService {
        $configurationCollection = new StorefrontPluginConfigurationCollection([$configuration]);

        $pluginRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $pluginRegistry->method('getConfigurations')->willReturn($configurationCollection);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->method('getFilesystemForStorefrontConfig')->willReturn(new Filesystem($assetRoot));

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        return new ThemeLifecycleService(
            $pluginRegistry,
            $repositories['theme'],
            $repositories['media'],
            $repositories['mediaFolder'],
            $repositories['themeMedia'],
            $fileSaver,
            $this->createMock(FileNameProvider::class),
            $themeFilesystemResolver,
            $repositories['language'],
            $repositories['themeChild'],
            $connection,
            $this->createMock(AbstractStorefrontPluginConfigurationFactory::class),
            $runtimeConfigService,
            $logger,
            $environment,
        );
    }

    private function createSystemLanguage(): LanguageEntity
    {
        $locale = new LocaleEntity();
        $locale->setId(Uuid::randomHex());
        $locale->setCode('en-GB');

        $language = new LanguageEntity();
        $language->setId(Uuid::randomHex());
        $language->setTranslationCode($locale);

        return $language;
    }

    private function createAssetRoot(): string
    {
        $root = sys_get_temp_dir() . '/theme-lifecycle-' . Uuid::randomHex();
        $path = $root . '/Resources/app/storefront/src/assets/image';

        static::assertTrue(mkdir($path, 0777, true));
        static::assertNotFalse(file_put_contents($path . '/shopware_logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'));

        return $root;
    }

    private function removeDirectory(string $path): void
    {
        (new SymfonyFilesystem())->remove($path);
    }
}
