<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\App\Aggregate\AppTranslation\AppTranslationCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Struct\BinaryCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Store\Struct\FaqCollection;
use Shopware\Core\Framework\Store\Struct\ImageCollection;
use Shopware\Core\Framework\Store\Struct\PermissionCollection;
use Shopware\Core\Framework\Store\Struct\StoreCategoryCollection;
use Shopware\Core\Framework\Store\Struct\StoreCollection;
use Shopware\Core\Framework\Store\Struct\VariantCollection;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Storefront\Framework\ThemeInterface;
use Symfony\Component\Intl\Languages;
use Symfony\Component\Intl\Locales;

/**
 * @internal
 */
class ExtensionLoader
{
    private const DEFAULT_LOCALE = 'en_GB';

    private EntityRepositoryInterface $themeRepository;

    /**
     * @var array<string>|null
     */
    private ?array $installedThemeNames = null;

    private AbstractAppLoader $appLoader;

    private ConfigurationService $configurationService;

    private EntityRepositoryInterface $languageRepository;

    private StoreService $storeService;

    public function __construct(
        EntityRepositoryInterface $languageRepository,
        EntityRepositoryInterface $themeRepository,
        AbstractAppLoader $appLoader,
        ConfigurationService $configurationService,
        StoreService $storeService
    ) {
        $this->languageRepository = $languageRepository;
        $this->themeRepository = $themeRepository;
        $this->appLoader = $appLoader;
        $this->configurationService = $configurationService;
        $this->storeService = $storeService;
    }

    public function loadFromArray(Context $context, array $data, ?string $locale = null): ExtensionStruct
    {
        if ($locale === null) {
            $locale = $this->storeService->getLanguageByContext($context);
        }

        $localeWithUnderscore = str_replace('-', '_', $locale);
        $data = $this->prepareArrayData($data, $localeWithUnderscore);

        return ExtensionStruct::fromArray($data);
    }

    public function loadFromListingArray(Context $context, array $data): ExtensionCollection
    {
        $locale = $this->storeService->getLanguageByContext($context);
        $localeWithUnderscore = str_replace('-', '_', $locale);
        $extensions = new ExtensionCollection();

        foreach ($data as $extension) {
            $extension = ExtensionStruct::fromArray($this->prepareArrayData($extension, $localeWithUnderscore));
            $extensions->set($extension->getName(), $extension);
        }

        return $extensions;
    }

    public function loadFromAppCollection(Context $context, AppCollection $collection): ExtensionCollection
    {
        $data = [];
        foreach ($collection as $app) {
            $data[] = $this->prepareAppData($context, $app);
        }

        $registeredApps = $this->loadFromListingArray($context, $data);

        // Enrich apps from filesystem
        $localApps = $this->loadLocalAppsCollection($context);

        foreach ($localApps as $name => $app) {
            if ($registeredApps->has($name)) {
                /** @var ExtensionStruct $registeredApp */
                $registeredApp = $registeredApps->get($name);

                $registeredApp->setIsTheme($app->isTheme());

                // Set version of local app to registered app if newer
                if (version_compare((string) $app->getVersion(), (string) $registeredApp->getVersion(), '>')) {
                    $registeredApp->setLatestVersion($app->getVersion());
                }

                continue;
            }

            $registeredApps->set($name, $app);
        }

        return $registeredApps;
    }

    public function loadFromPluginCollection(Context $context, PluginCollection $collection): ExtensionCollection
    {
        $extensions = new ExtensionCollection();

        foreach ($collection as $app) {
            $plugin = $this->loadFromPlugin($context, $app);
            $extensions->set($plugin->getName(), $plugin);
        }

        return $extensions;
    }

    public function getLocaleCodeFromLanguageId(Context $context, ?string $languageId = null): ?string
    {
        if ($languageId === null) {
            $languageId = $context->getLanguageId();
        }

        $id = $this->getLocalesCodesFromLanguageIds($context, [$languageId]);

        if (empty($id)) {
            return null;
        }

        return $id[0];
    }

    public function getLocalesCodesFromLanguageIds(Context $context, array $languageIds): array
    {
        $criteria = new Criteria($languageIds);
        $criteria->addAssociation('locale');
        $criteria->addSorting(new FieldSorting('name'));

        $languages = $this->languageRepository->search($criteria, $context)->getEntities();

        $codes = [];
        foreach ($languages as $language) {
            $codes[] = str_replace('-', '_', $language->getLocale()->getCode());
        }

        return $codes;
    }

    private function loadFromPlugin(Context $context, PluginEntity $plugin): ExtensionStruct
    {
        $isTheme = false;

        if (interface_exists(ThemeInterface::class) && class_exists($plugin->getBaseClass())) {
            $implementedInterfaces = class_implements($plugin->getBaseClass());

            if (\is_array($implementedInterfaces)) {
                $isTheme = \array_key_exists(ThemeInterface::class, $implementedInterfaces);
            }
        }

        $data = [
            'localId' => $plugin->getId(),
            'description' => $plugin->getTranslation('description'),
            'name' => $plugin->getName(),
            'label' => $plugin->getTranslation('label'),
            'producerName' => $plugin->getAuthor(),
            'license' => $plugin->getLicense(),
            'version' => $plugin->getVersion(),
            'latestVersion' => $plugin->getUpgradeVersion(),
            'iconRaw' => $plugin->getIcon(),
            'installedAt' => $plugin->getInstalledAt(),
            'active' => $plugin->getActive(),
            'type' => ExtensionStruct::EXTENSION_TYPE_PLUGIN,
            'isTheme' => $isTheme,
            'configurable' => $this->configurationService->checkConfiguration(sprintf('%s.config', $plugin->getName()), $context),
            'updatedAt' => $plugin->getUpgradedAt(),
        ];

        return ExtensionStruct::fromArray($this->replaceCollections($data));
    }

    /**
     * @return array<string>
     */
    private function getInstalledThemeNames(Context $context): array
    {
        if ($this->installedThemeNames === null) {
            $themeNameAggregationName = 'theme_names';
            $criteria = new Criteria();
            $criteria->addAggregation(new TermsAggregation($themeNameAggregationName, 'technicalName'));

            /** @var TermsResult $themeNameAggregation */
            $themeNameAggregation = $this->themeRepository->aggregate($criteria, $context)->get($themeNameAggregationName);

            return $this->installedThemeNames = $themeNameAggregation->getKeys();
        }

        return $this->installedThemeNames;
    }

    private function loadLocalAppsCollection(Context $context): ExtensionCollection
    {
        $apps = $this->appLoader->load();
        $collection = new ExtensionCollection();
        $language = $this->storeService->getLanguageByContext($context);

        foreach ($apps as $name => $app) {
            $icon = $this->appLoader->getIcon($app);

            $appArray = $app->getMetadata()->toArray($language);

            $row = [
                'description' => isset($appArray['description']) ? $this->getTranslationFromArray($appArray['description'], $language) : '',
                'name' => $name,
                'label' => isset($appArray['label']) ? $this->getTranslationFromArray($appArray['label'], $language) : '',
                'producerName' => $app->getMetadata()->getAuthor(),
                'license' => $app->getMetadata()->getLicense(),
                'version' => $app->getMetadata()->getVersion(),
                'latestVersion' => $app->getMetadata()->getVersion(),
                'iconRaw' => $icon ? base64_encode($icon) : null,
                'installedAt' => null,
                'active' => false,
                'type' => ExtensionStruct::EXTENSION_TYPE_APP,
                'isTheme' => is_file($app->getPath() . '/Resources/theme.json'),
                'privacyPolicyExtension' => isset($appArray['privacyPolicyExtensions']) ? $this->getTranslationFromArray($appArray['privacyPolicyExtensions'], $language, 'en-GB') : '',
                'privacyPolicyLink' => $app->getMetadata()->getPrivacy(),
            ];

            $collection->set($name, $this->loadFromArray($context, $row, $language));
        }

        return $collection;
    }

    private function prepareArrayData(array $data, ?string $locale): array
    {
        return $this->translateExtensionLanguages($this->replaceCollections($data), $locale);
    }

    private function prepareAppData(Context $context, AppEntity $app): array
    {
        $installedThemeNames = $this->getInstalledThemeNames($context);

        $data = [
            'localId' => $app->getId(),
            'description' => $app->getDescription(),
            'name' => $app->getName(),
            'label' => $app->getLabel(),
            'producerName' => $app->getAuthor(),
            'license' => $app->getLicense(),
            'version' => $app->getVersion(),
            'privacyPolicyLink' => $app->getPrivacy(),
            'iconRaw' => $app->getIcon(),
            'installedAt' => $app->getCreatedAt(),
            'permissions' => $app->getAclRole() !== null ? $this->makePermissionArray($app->getAclRole()->getPrivileges()) : [],
            'active' => $app->isActive(),
            'languages' => [],
            'type' => ExtensionStruct::EXTENSION_TYPE_APP,
            'isTheme' => \in_array($app->getName(), $installedThemeNames, true),
            'configurable' => $app->isConfigurable(),
            'privacyPolicyExtension' => $app->getPrivacyPolicyExtensions(),
            'updatedAt' => $app->getUpdatedAt(),
        ];

        $appTranslations = $app->getTranslations();

        if ($appTranslations) {
            $data['languages'] = $this->makeLanguagesArray($context, $appTranslations);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, StoreCollection|array<string>|null>
     */
    private function replaceCollections(array $data): array
    {
        $replacements = [
            'variants' => VariantCollection::class,
            'faq' => FaqCollection::class,
            'binaries' => BinaryCollection::class,
            'images' => ImageCollection::class,
            'categories' => StoreCategoryCollection::class,
            'permissions' => PermissionCollection::class,
        ];

        foreach ($replacements as $key => $collectionClass) {
            $data[$key] = new $collectionClass($data[$key] ?? []);
        }

        return $data;
    }

    private function makePermissionArray(array $appPrivileges): array
    {
        $permissions = [];

        foreach ($appPrivileges as $privilege) {
            $entityAndOperation = explode(':', $privilege);
            $permissions[] = array_combine(['entity', 'operation'], $entityAndOperation);
        }

        return $permissions;
    }

    private function translateExtensionLanguages(array $data, ?string $locale = self::DEFAULT_LOCALE): array
    {
        if (!isset($data['languages'])) {
            return $data;
        }

        $locale = $locale && Locales::exists($locale) ? $locale : self::DEFAULT_LOCALE;

        foreach ($data['languages'] as $key => $language) {
            $data['languages'][$key] = Languages::getName($language['name'], $locale);
        }

        return $data;
    }

    private function makeLanguagesArray(Context $context, AppTranslationCollection $translations): array
    {
        $languageIds = array_map(
            static function ($translation) {
                return $translation->getLanguageId();
            },
            $translations->getElements()
        );

        $translationLocales = $this->getLocalesCodesFromLanguageIds($context, $languageIds);

        return array_map(
            static function ($translationLocale) {
                return ['name' => $translationLocale];
            },
            $translationLocales
        );
    }

    private function getTranslationFromArray(array $translations, string $currentLanguage, string $fallbackLanguage = self::DEFAULT_LOCALE): ?string
    {
        if (isset($translations[$currentLanguage])) {
            return $translations[$currentLanguage];
        }

        if (isset($translations[$fallbackLanguage])) {
            return $translations[$fallbackLanguage];
        }

        return null;
    }
}
