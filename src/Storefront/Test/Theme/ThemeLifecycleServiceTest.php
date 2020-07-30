<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithFileAssociations\ThemeWithFileAssociations;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithLabels\ThemeWithLabels;
use Shopware\Storefront\Theme\Aggregate\ThemeTranslationCollection;
use Shopware\Storefront\Theme\Aggregate\ThemeTranslationEntity;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeLifecycleService;

class ThemeLifecycleServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var ThemeLifecycleService
     */
    private $themeLifecycleService;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepositoryInterface
     */
    private $themeRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    public function setUp(): void
    {
        $this->themeLifecycleService = $this->getContainer()->get(ThemeLifecycleService::class);
        $this->themeRepository = $this->getContainer()->get('theme.repository');
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testItRegistersANewThemeCorrectly(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertTrue($themeEntity->isActive());
        static::assertEquals(2, $themeEntity->getMedia()->count());
    }

    public function testYouCanUpdateConfigToAddNewMedia(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);
        $this->addPinkLogoToTheme($bundle);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertTrue($themeEntity->isActive());
        static::assertEquals(3, $themeEntity->getMedia()->count());
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

        $renamedShopwareLogoId = $this->getMedia('shopware_logo_(1)');
        static::assertNull($this->getMedia('shopware_logo_pink_(1)'));
        static::assertNotNull($themeEntity->getMedia()->get($renamedShopwareLogoId->getId()));
    }

    public function testItSkipsTranslationsIfLanguageIsNotAvailable(): void
    {
        $bundle = $this->getThemeConfigWithLabels();
        $this->deleteLanguageForLocale('de-DE');

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $theme = $this->getTheme($bundle);

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

    private function getTheme(StorefrontPluginConfiguration $bundle): ThemeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $bundle->getTechnicalName()));
        $criteria->addAssociation('media');
        $criteria->addAssociation('translations.language.locale');

        return $this->themeRepository->search($criteria, $this->context)->getEntities()->first();
    }

    private function getMedia(string $fileName): ?MediaEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));

        // will throw if media does not exists
        return $this->mediaRepository->search($criteria, $this->context)->first();
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
        /** @var EntityRepositoryInterface $languageRepository */
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
        /** @var EntityRepositoryInterface $languageRepository */
        $languageRepository = $this->getContainer()->get('language.repository');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', Defaults::LANGUAGE_SYSTEM));

        /** @var LanguageEntity $language */
        $language = $languageRepository->search($criteria, $context)->first();

        /** @var EntityRepositoryInterface $localeRepository */
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
            return $locale === $translation->getLanguage()->getLocale()->getCode();
        })->first();
    }
}
