<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithFileAssociations\ThemeWithFileAssociations;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithLabels\ThemeWithLabels;
use Shopware\Storefront\Theme\Aggregate\ThemeTranslationCollection;
use Shopware\Storefront\Theme\Aggregate\ThemeTranslationEntity;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeLifecycleService;

/**
 * @internal
 */
class ThemeLifecycleServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ThemeLifecycleService $themeLifecycleService;

    private Context $context;

    private EntityRepository $themeRepository;

    private EntityRepository $mediaRepository;

    private EntityRepository $mediaFolderRepository;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->themeLifecycleService = $this->getContainer()->get(ThemeLifecycleService::class);
        $this->themeRepository = $this->getContainer()->get('theme.repository');
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->mediaFolderRepository = $this->getContainer()->get('media_folder.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testItRegistersANewThemeCorrectly(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertTrue($themeEntity->isActive());
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        static::assertEquals(2, $themeEntity->getMedia()->count());

        $themeDefaultFolderId = $this->getThemeMediaDefaultFolderId();
        foreach ($themeEntity->getMedia() as $media) {
            static::assertEquals($themeDefaultFolderId, $media->getMediaFolderId());
        }
    }

    public function testThemeConfigInheritanceAddsParentTheme(): void
    {
        $parentBundle = $this->getThemeConfigWithLabels();
        $this->themeLifecycleService->refreshTheme($parentBundle, $this->context);
        $bundle = $this->getThemeConfig();
        $bundle->setConfigInheritance(['@' . $parentBundle->getTechnicalName()]);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $parentThemeEntity = $this->getTheme($parentBundle);
        $themeEntity = $this->getTheme($bundle);

        static::assertEquals($parentThemeEntity->getId(), $themeEntity->getParentThemeId());
    }

    public function testThemeRefreshWithParentTheme(): void
    {
        $parentBundle = $this->getThemeConfigWithLabels();
        $this->themeLifecycleService->refreshTheme($parentBundle, $this->context);
        $bundle = $this->getThemeConfig();
        $bundle->setConfigInheritance(['@' . $parentBundle->getTechnicalName()]);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $parentThemeEntity = $this->getTheme($parentBundle);
        $themeEntity = $this->getTheme($bundle);

        static::assertEquals($parentThemeEntity->getId(), $themeEntity->getParentThemeId());

        $bundle->setConfigInheritance([]);
        $this->themeLifecycleService->refreshTheme($parentBundle, $this->context);

        $themeEntity = $this->getTheme($bundle);
        static::assertEquals($parentThemeEntity->getId(), $themeEntity->getParentThemeId());
    }

    public function testYouCanUpdateConfigToAddNewMedia(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);
        $this->addPinkLogoToTheme($bundle);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertTrue($themeEntity->isActive());
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        static::assertEquals(5, $themeEntity->getMedia()->count());
    }

    public function testItWontThrowIfMediaHasRestrictDeleteAssociation(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $shopwareLogoId = $this->getMedia('shopware_logo');
        $this->createCmsPage($shopwareLogoId->getId());

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        // assert that the file shopware_logo was not deleted and is assigned to same media entity as before
        static::assertEquals($shopwareLogoId, $this->getMedia('shopware_logo'));
    }

    public function testItRenamesThemeMediaIfItExistsBefore(): void
    {
        $bundle = $this->getThemeConfig();
        $this->addPinkLogoToTheme($bundle);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $shopwareLogoId = $this->getMedia('shopware_logo');
        $this->createCmsPage($shopwareLogoId->getId());

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        $renamedShopwareLogoId = $this->getMedia('shopware_logo_(1)');
        static::assertNotNull($themeEntity->getMedia()->get($renamedShopwareLogoId->getId()));
    }

    public function testItUploadsFilesIntoTheRootFolderIfThemeDefaultFolderDoesNotExist(): void
    {
        $bundle = $this->getThemeConfig();
        $themeMediaDefaultFolderId = $this->getThemeMediaDefaultFolderId();

        $this->connection->executeStatement('
            UPDATE `media`
            SET `media_folder_id` = null
            WHERE `media_folder_id` = :defaultThemeFolder
        ', ['defaultThemeFolder' => Uuid::fromHexToBytes($themeMediaDefaultFolderId)]);
        $this->mediaFolderRepository->delete([['id' => $themeMediaDefaultFolderId]], $this->context);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertTrue($themeEntity->isActive());
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        static::assertEquals(2, $themeEntity->getMedia()->count());

        foreach ($themeEntity->getMedia() as $media) {
            static::assertNull($media->getMediaFolderId());
        }
    }

    public function testItDoesNotOverridePreviewIfSetExclusive(): void
    {
        $previewMediaId = Uuid::randomHex();
        $this->mediaRepository->create([
            [
                'id' => $previewMediaId,
            ],
        ], $this->context);

        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $theme = $this->getTheme($bundle);
        $this->themeRepository->update([
            [
                'id' => $theme->getId(),
                'previewMediaId' => $previewMediaId,
            ],
        ], $this->context);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $theme = $this->getTheme($bundle);
        static::assertEquals($previewMediaId, $theme->getPreviewMediaId());
    }

    public function testItSkipsTranslationsIfLanguageIsNotAvailable(): void
    {
        $bundle = $this->getThemeConfigWithLabels();
        $this->deleteLanguageForLocale('de-DE');

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $theme = $this->getTheme($bundle);

        static::assertInstanceOf(ThemeTranslationCollection::class, $theme->getTranslations());
        static::assertCount(1, $theme->getTranslations());
        static::assertEquals('en-GB', $theme->getTranslations()->first()->getLanguage()->getLocale()->getCode());
        static::assertEquals([
            'fields.sw-image' => 'test label',
        ], $theme->getTranslations()->first()->getLabels());
        static::assertEquals([
            'fields.sw-image' => 'test help',
        ], $theme->getTranslations()->first()->getHelpTexts());
    }

    public function testItUsesEnglishTranslationsAsFallbackIfDefaultLanguageIsNotProvided(): void
    {
        $bundle = $this->getThemeConfigWithLabels();
        $this->changeDefaultLanguageLocale('xx-XX');

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $theme = $this->getTheme($bundle);

        static::assertInstanceOf(ThemeTranslationCollection::class, $theme->getTranslations());
        static::assertCount(2, $theme->getTranslations());
        $translation = $this->getTranslationByLocale('xx-XX', $theme->getTranslations());
        static::assertEquals([
            'fields.sw-image' => 'test label',
        ], $translation->getLabels());
        static::assertEquals([
            'fields.sw-image' => 'test help',
        ], $translation->getHelpTexts());

        $germanTranslation = $this->getTranslationByLocale('de-DE', $theme->getTranslations());
        static::assertEquals([
            'fields.sw-image' => 'Test label',
        ], $germanTranslation->getLabels());
        static::assertEquals([
            'fields.sw-image' => 'Test Hilfe',
        ], $germanTranslation->getHelpTexts());
    }

    public function testItRemovesAThemeCorrectly(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        $themeMedia = $themeEntity->getMedia();
        $ids = $themeMedia->getIds();

        static::assertTrue($themeEntity->isActive());
        static::assertEquals(2, $themeMedia->count());

        $themeDefaultFolderId = $this->getThemeMediaDefaultFolderId();
        foreach ($themeMedia as $media) {
            static::assertEquals($themeDefaultFolderId, $media->getMediaFolderId());
        }

        $this->themeLifecycleService->removeTheme($bundle->getTechnicalName(), $this->context);

        // check whether the theme is no longer in the table and the associated media have been deleted
        static::assertFalse($this->hasTheme($bundle));
        static::assertCount(0, $this->mediaRepository->searchIds(new Criteria($ids), Context::createDefaultContext())->getIds());
    }

    public function testItRemovesAChildThemeCorrectly(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle, true);
        $childId = Uuid::randomHex();

        static::assertInstanceOf(ThemeCollection::class, $themeEntity->getDependentThemes());
        // check if we have no dependent Themes
        static::assertEquals(0, $themeEntity->getDependentThemes()->count());

        // clone theme and make it child
        $this->themeRepository->clone($themeEntity->getId(), $this->context, $childId, new CloneBehavior([
            'technicalName' => null,
            'name' => 'Cloned theme',
            'parentThemeId' => $themeEntity->getId(),
        ]));

        // refresh theme to get child
        $themeEntity = $this->getTheme($bundle, true);

        $themeMedia = $themeEntity->getMedia();
        static::assertInstanceOf(MediaCollection::class, $themeMedia);
        $ids = $themeMedia->getIds();

        static::assertTrue($themeEntity->isActive());
        static::assertEquals(2, $themeMedia->count());
        static::assertInstanceOf(ThemeCollection::class, $themeEntity->getDependentThemes());
        static::assertEquals(1, $themeEntity->getDependentThemes()->count());

        $themeDefaultFolderId = $this->getThemeMediaDefaultFolderId();
        foreach ($themeMedia as $media) {
            static::assertEquals($themeDefaultFolderId, $media->getMediaFolderId());
        }

        $this->themeLifecycleService->removeTheme($bundle->getTechnicalName(), $this->context);

        // check whether the theme is no longer in the table and the associated media have been deleted
        static::assertFalse($this->hasTheme($bundle));
        static::assertCount(0, $this->mediaRepository->searchIds(new Criteria($ids), Context::createDefaultContext())->getIds());
        static::assertEquals(0, $this->themeRepository->search(new Criteria([$childId, $themeEntity->getId()]), $this->context)->count());
    }

    private function getThemeConfig(): StorefrontPluginConfiguration
    {
        $factory = $this->getContainer()->get(StorefrontPluginConfigurationFactory::class);

        return $factory->createFromBundle(new ThemeWithFileAssociations());
    }

    private function getThemeConfigWithLabels(): StorefrontPluginConfiguration
    {
        $factory = $this->getContainer()->get(StorefrontPluginConfigurationFactory::class);

        return $factory->createFromBundle(new ThemeWithLabels());
    }

    private function getTheme(StorefrontPluginConfiguration $bundle, bool $withChild = false): ThemeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $bundle->getTechnicalName()));
        $criteria->addAssociation('media');
        $criteria->addAssociation('translations.language.locale');

        if ($withChild) {
            $criteria->addAssociation('dependentThemes');
        }

        $theme = $this->themeRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(ThemeEntity::class, $theme);

        return $theme;
    }

    private function hasTheme(StorefrontPluginConfiguration $bundle): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $bundle->getTechnicalName()));

        return $this->themeRepository->searchIds($criteria, $this->context)->getTotal() > 0;
    }

    private function getMedia(string $fileName): MediaEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));

        $media = $this->mediaRepository->search($criteria, $this->context)->first();
        static::assertInstanceOf(MediaEntity::class, $media);

        return $media;
    }

    // we create a cms-page because it has has the DeleteRestricted flag in media definition
    private function createCmsPage(string $logoId): void
    {
        $manufacturerRepository = $this->getContainer()->get('cms_page.repository');
        $manufacturerRepository->create([[
            'name' => 'dummy cms page',
            'previewMediaId' => $logoId,
            'type' => 'page',
            'config' => [],
        ]], $this->context);
    }

    private function addPinkLogoToTheme(StorefrontPluginConfiguration $bundle): void
    {
        $config = $bundle->getThemeConfig();
        $config['fields']['shopwareLogoPink'] = [
            'label' => [
                'en-GB' => 'shopware_logo_pink',
                'de-DE' => 'shopware_logo_pink',
            ],
            'type' => 'media',
            'value' => 'app/storefront/src/assets/image/shopware_logo_pink.svg',
        ];

        $bundle->setThemeConfig($config);
    }

    private function deleteLanguageForLocale(string $locale): void
    {
        /** @var EntityRepository $languageRepository */
        $languageRepository = $this->getContainer()->get('language.repository');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translationCode.code', $locale));

        $id = $languageRepository->searchIds($criteria, $context)->firstId();

        $languageRepository->delete([
            ['id' => $id],
        ], $context);
    }

    private function changeDefaultLanguageLocale(string $locale): void
    {
        /** @var EntityRepository $languageRepository */
        $languageRepository = $this->getContainer()->get('language.repository');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', Defaults::LANGUAGE_SYSTEM));

        /** @var LanguageEntity $language */
        $language = $languageRepository->search($criteria, $context)->first();

        /** @var EntityRepository $localeRepository */
        $localeRepository = $this->getContainer()->get('locale.repository');

        $localeRepository->upsert([
            [
                'id' => $language->getTranslationCodeId(),
                'code' => $locale,
            ],
        ], $context);
    }

    private function getTranslationByLocale(string $locale, ThemeTranslationCollection $translations): ThemeTranslationEntity
    {
        return $translations->filter(static function (ThemeTranslationEntity $translation) use ($locale): bool {
            static::assertInstanceOf(LanguageEntity::class, $translation->getLanguage());
            static::assertInstanceOf(LocaleEntity::class, $translation->getLanguage()->getLocale());

            return $locale === $translation->getLanguage()->getLocale()->getCode();
        })->first();
    }

    private function getThemeMediaDefaultFolderId(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', 'theme'));
        $criteria->addAssociation('defaultFolder');
        $criteria->setLimit(1);
        /** @var MediaFolderCollection $defaultFolder */
        $defaultFolder = $this->mediaFolderRepository->search($criteria, $this->context)->getEntities();

        if ($defaultFolder->count() !== 1) {
            throw new \RuntimeException('Default Theme folder does not exist.');
        }

        return $defaultFolder->first()->getId();
    }
}
