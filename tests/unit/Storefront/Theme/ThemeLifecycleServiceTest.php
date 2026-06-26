<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\File\WindowsStyleFileNameProvider;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
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

    /**
     * @var StaticEntityRepository<ThemeCollection>
     */
    private StaticEntityRepository $themeRepository;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

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

        $this->lifecycleService = $this->createLifecycleService('test');
    }

    protected function tearDown(): void
    {
        (new SymfonyFilesystem())->remove($this->assetRoot);
    }

    public function testRefreshThemeLogsAndSkipsMediaImportFailuresOutsideDev(): void
    {
        $themeConfig = $this->configuration->getThemeConfig();
        static::assertIsArray($themeConfig);
        $themeConfig['fields']['sameBrokenMedia'] = [
            'type' => 'media',
            'value' => 'app/storefront/src/assets/image/shopware_logo.svg',
        ];
        $this->configuration->setThemeConfig($themeConfig);

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
        static::assertNull($themePayload['baseConfig']['fields']['sameBrokenMedia']['value']);
    }

    public function testRefreshThemeRemovesFailedPreviewMediaOutsideDev(): void
    {
        $this->configuration->setThemeConfig([]);
        $this->configuration->setPreviewMedia('app/storefront/src/assets/image/shopware_logo.svg');

        $exception = MediaException::invalidFile('Broken preview media');

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
        static::assertSame([['id' => $failedMediaId]], $this->mediaRepository->deletes[0]);

        $themePayload = $this->themeRepository->upserts[0][0];
        static::assertSame([], $themePayload['media']);
        static::assertArrayNotHasKey('previewMediaId', $themePayload);
    }

    public function testRefreshThemeRetriesThemeMediaImportWithNewNameWhenFileNameExists(): void
    {
        $existingMedia = new MediaEntity();
        $existingMedia->setId(Uuid::randomHex());
        $existingMedia->setFileName('shopware_logo');
        $existingMedia->setFileExtension('svg');
        $existingMedia->setMimeType('image/svg+xml');

        $this->mediaRepository->addSearch(new MediaCollection([$existingMedia]));

        $duplicateFileName = MediaException::duplicatedMediaFileName('shopware_logo', 'svg');
        $failedMediaId = null;
        $call = 0;

        $this->fileSaver->expects($this->exactly(2))->method('persistFileToMedia')->willReturnCallback(function (MediaFile $mediaFile, string $destination, string $mediaId, Context $context) use (&$call, &$failedMediaId, $duplicateFileName): void {
            ++$call;

            static::assertSame('svg', $mediaFile->getFileExtension());
            static::assertSame($this->context, $context);

            if ($call === 1) {
                static::assertSame('shopware_logo', $destination);
                $failedMediaId = $mediaId;

                throw $duplicateFileName;
            }

            static::assertSame('shopware_logo_(1)', $destination);
            static::assertSame($failedMediaId, $mediaId);
        });

        $this->logger->expects($this->never())->method('error');
        $this->runtimeConfigService->expects($this->once())->method('refreshRuntimeConfig');
        $this->runtimeConfigService->expects($this->once())->method('resetCaches');

        $this->lifecycleService->refreshTheme($this->configuration, $this->context);

        static::assertIsString($failedMediaId);
        static::assertSame($failedMediaId, $this->mediaRepository->creates[0][0]['id']);

        $themePayload = $this->themeRepository->upserts[0][0];
        static::assertSame([['id' => $failedMediaId, 'mediaFolderId' => null]], $themePayload['media']);
        static::assertSame($failedMediaId, $themePayload['baseConfig']['fields']['brokenMedia']['value']);
    }

    public function testRefreshThemeRethrowsMediaImportFailuresInDev(): void
    {
        $this->lifecycleService = $this->createLifecycleService('dev');

        $exception = MediaException::invalidFile('Broken media');

        $this->fileSaver->expects($this->once())->method('persistFileToMedia')->willThrowException($exception);

        $this->logger->expects($this->never())->method('error');

        $this->runtimeConfigService->expects($this->never())->method('refreshRuntimeConfig');
        $this->runtimeConfigService->expects($this->never())->method('resetCaches');
        $this->expectExceptionObject($exception);

        $this->lifecycleService->refreshTheme($this->configuration, $this->context);
    }

    private function createLifecycleService(string $environment): ThemeLifecycleService
    {
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
        /** @var StaticEntityRepository<EntityCollection<Entity>> $themeMediaRepository */
        $themeMediaRepository = new StaticEntityRepository([]);
        /** @var StaticEntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new StaticEntityRepository([new LanguageCollection([$language])]);
        /** @var StaticEntityRepository<EntityCollection<Entity>> $themeChildRepository */
        $themeChildRepository = new StaticEntityRepository([[]]);

        $configurationCollection = new StorefrontPluginConfigurationCollection([$this->configuration]);

        $pluginRegistry = static::createStub(StorefrontPluginRegistry::class);
        $pluginRegistry->method('getConfigurations')->willReturn($configurationCollection);

        $themeFilesystemResolver = static::createStub(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->method('getFilesystemForStorefrontConfig')->willReturn(new Filesystem($this->assetRoot));

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $fileNameProvider = new WindowsStyleFileNameProvider($this->mediaRepository);
        $pluginConfigurationFactory = static::createStub(AbstractStorefrontPluginConfigurationFactory::class);

        return new ThemeLifecycleService(
            $pluginRegistry,
            $this->themeRepository,
            $this->mediaRepository,
            $mediaFolderRepository,
            $themeMediaRepository,
            $this->fileSaver,
            $fileNameProvider,
            $themeFilesystemResolver,
            $languageRepository,
            $themeChildRepository,
            $connection,
            $pluginConfigurationFactory,
            $this->runtimeConfigService,
            $this->logger,
            $environment,
        );
    }
}
