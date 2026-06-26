<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
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
    private Context $context;

    private StorefrontPluginConfiguration $configuration;

    private string $assetRoot;

    private FileSaver&MockObject $fileSaver;

    private LoggerInterface&MockObject $logger;

    private ThemeRuntimeConfigService&MockObject $runtimeConfigService;

    private ThemeLifecycleService $lifecycleService;

    private ThemeLifecycleService $devLifecycleService;

    /**
     * @var StaticEntityRepository<ThemeCollection>
     */
    private StaticEntityRepository $themeRepository;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    /**
     * @var StaticEntityRepository<MediaFolderCollection>
     */
    private StaticEntityRepository $mediaFolderRepository;

    /**
     * @var StaticEntityRepository<EntityCollection<Entity>>
     */
    private StaticEntityRepository $themeMediaRepository;

    /**
     * @var StaticEntityRepository<LanguageCollection>
     */
    private StaticEntityRepository $languageRepository;

    /**
     * @var StaticEntityRepository<EntityCollection<Entity>>
     */
    private StaticEntityRepository $themeChildRepository;

    private StorefrontPluginRegistry&Stub $pluginRegistry;

    private ThemeFilesystemResolver&Stub $themeFilesystemResolver;

    private Connection&Stub $connection;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $this->configuration = new StorefrontPluginConfiguration('TestTheme');
        $this->configuration->setName('TestTheme');
        $this->configuration->setAuthor('shopware AG');
        $this->configuration->setIsTheme(true);
        $this->configuration->setThemeJson([]);
        $this->configuration->setThemeConfig([
            'fields' => [
                'brokenMedia' => [
                    'type' => 'media',
                    'value' => 'app/storefront/src/assets/image/shopware_logo.svg',
                ],
            ],
        ]);

        $this->assetRoot = sys_get_temp_dir() . '/theme-lifecycle-' . Uuid::randomHex();
        $path = $this->assetRoot . '/Resources/app/storefront/src/assets/image';

        static::assertTrue(mkdir($path, 0777, true));
        static::assertNotFalse(file_put_contents($path . '/shopware_logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'));

        $this->fileSaver = $this->createMock(FileSaver::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);

        $locale = new LocaleEntity();
        $locale->setId(Uuid::randomHex());
        $locale->setCode('en-GB');

        $language = new LanguageEntity();
        $language->setId(Uuid::randomHex());
        $language->setTranslationCode($locale);

        /** @var StaticEntityRepository<ThemeCollection> $themeRepository */
        $themeRepository = new StaticEntityRepository([new ThemeCollection()], new ThemeDefinition());
        $this->themeRepository = $themeRepository;
        /** @var StaticEntityRepository<MediaCollection> $mediaRepository */
        $mediaRepository = new StaticEntityRepository([[]], new MediaDefinition());
        $this->mediaRepository = $mediaRepository;
        /** @var StaticEntityRepository<MediaFolderCollection> $mediaFolderRepository */
        $mediaFolderRepository = new StaticEntityRepository([[]]);
        $this->mediaFolderRepository = $mediaFolderRepository;
        /** @var StaticEntityRepository<EntityCollection<Entity>> $themeMediaRepository */
        $themeMediaRepository = new StaticEntityRepository([]);
        $this->themeMediaRepository = $themeMediaRepository;
        /** @var StaticEntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new StaticEntityRepository([new LanguageCollection([$language])]);
        $this->languageRepository = $languageRepository;
        /** @var StaticEntityRepository<EntityCollection<Entity>> $themeChildRepository */
        $themeChildRepository = new StaticEntityRepository([[]]);
        $this->themeChildRepository = $themeChildRepository;

        $configurationCollection = new StorefrontPluginConfigurationCollection([$this->configuration]);

        $this->pluginRegistry = static::createStub(StorefrontPluginRegistry::class);
        $this->pluginRegistry->method('getConfigurations')->willReturn($configurationCollection);

        $this->themeFilesystemResolver = static::createStub(ThemeFilesystemResolver::class);
        $this->themeFilesystemResolver->method('getFilesystemForStorefrontConfig')->willReturn(new Filesystem($this->assetRoot));

        $this->connection = static::createStub(Connection::class);
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $fileNameProvider = static::createStub(FileNameProvider::class);
        $pluginConfigurationFactory = static::createStub(AbstractStorefrontPluginConfigurationFactory::class);

        $createLifecycleService = function (string $environment) use ($fileNameProvider, $pluginConfigurationFactory): ThemeLifecycleService {
            return new ThemeLifecycleService(
                $this->pluginRegistry,
                $this->themeRepository,
                $this->mediaRepository,
                $this->mediaFolderRepository,
                $this->themeMediaRepository,
                $this->fileSaver,
                $fileNameProvider,
                $this->themeFilesystemResolver,
                $this->languageRepository,
                $this->themeChildRepository,
                $this->connection,
                $pluginConfigurationFactory,
                $this->runtimeConfigService,
                $this->logger,
                $environment,
            );
        };

        $this->lifecycleService = $createLifecycleService('test');
        $this->devLifecycleService = $createLifecycleService('dev');
    }

    protected function tearDown(): void
    {
        (new SymfonyFilesystem())->remove($this->assetRoot);
    }

    public function testRefreshThemeLogsAndSkipsMediaImportFailuresOutsideDev(): void
    {
        $exception = MediaException::invalidFile('Broken media');

        $this->fileSaver->expects($this->once())->method('persistFileToMedia')->willThrowException($exception);

        $failedMediaId = null;
        $this->logger->expects($this->once())->method('error')->with(
            'Could not import theme media file.',
            static::callback(function (array $logContext) use (&$failedMediaId, $exception): bool {
                static::assertSame($this->configuration->getTechnicalName(), $logContext['theme'] ?? null);
                static::assertSame('app/storefront/src/assets/image/shopware_logo.svg', $logContext['path'] ?? null);
                static::assertSame($exception, $logContext['exception'] ?? null);
                static::assertArrayHasKey('mediaId', $logContext);
                static::assertIsString($logContext['mediaId']);

                $failedMediaId = $logContext['mediaId'];

                return true;
            })
        );

        $this->runtimeConfigService->expects($this->once())->method('refreshRuntimeConfig');
        $this->runtimeConfigService->expects($this->once())->method('resetCaches');

        $this->lifecycleService->refreshTheme($this->configuration, $this->context);

        static::assertIsString($failedMediaId);
        static::assertSame($failedMediaId, $this->mediaRepository->creates[0][0]['id']);
        static::assertNull($this->mediaRepository->creates[0][0]['mediaFolderId']);
        static::assertSame([['id' => $failedMediaId]], $this->mediaRepository->deletes[0]);

        $themePayload = $this->themeRepository->upserts[0][0];
        static::assertSame([], $themePayload['media']);
        static::assertNull($themePayload['baseConfig']['fields']['brokenMedia']['value']);
    }

    public function testRefreshThemeRethrowsMediaImportFailuresInDev(): void
    {
        $exception = MediaException::invalidFile('Broken media');

        $this->fileSaver->expects($this->once())->method('persistFileToMedia')->willThrowException($exception);

        $this->logger->expects($this->never())->method('error');

        $this->runtimeConfigService->expects($this->never())->method('refreshRuntimeConfig');
        $this->runtimeConfigService->expects($this->never())->method('resetCaches');
        $this->expectExceptionObject($exception);

        $this->devLifecycleService->refreshTheme($this->configuration, $this->context);
    }
}
