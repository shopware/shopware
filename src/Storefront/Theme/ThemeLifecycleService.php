<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\MimeType;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;

#[Package('storefront')]
class ThemeLifecycleService
{
    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     *
     * @internal
     */
    public function __construct(
        private readonly StorefrontPluginRegistryInterface $pluginRegistry,
        private readonly EntityRepository $themeRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $mediaFolderRepository,
        private readonly EntityRepository $themeMediaRepository,
        private readonly FileSaver $fileSaver,
        private readonly FileNameProvider $fileNameProvider,
        private readonly ThemeFilesystemResolver $themeFilesystemResolver,
        private readonly EntityRepository $languageRepository,
        private readonly EntityRepository $themeChildRepository,
        private readonly Connection $connection,
        private readonly AbstractStorefrontPluginConfigurationFactory $pluginConfigurationFactory
    ) {
    }

    public function refreshThemes(
        Context $context,
        ?StorefrontPluginConfigurationCollection $configurationCollection = null
    ): void {
        if ($configurationCollection === null) {
            $configurationCollection = $this->pluginRegistry->getConfigurations()->getThemes();
        }

        // iterate over all theme configs in the filesystem (plugins/bundles)
        foreach ($configurationCollection as $config) {
            $this->refreshTheme($config, $context);
        }
    }

    public function refreshTheme(StorefrontPluginConfiguration $configuration, Context $context): void
    {
        $themeData = [];
        $themeData['name'] = $configuration->getName();
        $themeData['technicalName'] = $configuration->getTechnicalName();
        $themeData['author'] = $configuration->getAuthor();
        $themeData['themeJson'] = $configuration->getThemeJson();

        // refresh theme after deleting media
        $theme = $this->getThemeByTechnicalName($configuration->getTechnicalName(), $context);

        // check if theme config already exists in the database
        if ($theme) {
            $themeData['id'] = $theme->getId();
        } else {
            $themeData['active'] = true;
        }

        $themeData['translations'] = $this->getTranslationsConfiguration($configuration, $context);

        $updatedData = $this->updateMediaInConfiguration($theme, $configuration, $context);

        $themeData = array_merge($themeData, $updatedData);

        if (!empty($configuration->getConfigInheritance())) {
            $themeData = $this->addParentTheme($configuration, $themeData, $context);
        }

        $writtenEvent = $this->themeRepository->upsert([$themeData], $context);

        if (empty($themeData['id'])) {
            $themeData['id'] = current($writtenEvent->getPrimaryKeys(ThemeDefinition::ENTITY_NAME));
        }

        $this->themeRepository->upsert([$themeData], $context);

        if (!empty($themeData['toDeleteMedia'])) {
            $this->themeMediaRepository->delete($themeData['toDeleteMedia'], $context);
        }

        $parentThemes = $this->getParentThemes($configuration, $themeData['id']);
        $parentCriteria = new Criteria();
        $parentCriteria->addFilter(new EqualsFilter('childId', $themeData['id']));
        /** @var list<array<string, string>> $toDeleteIds */
        $toDeleteIds = $this->themeChildRepository->searchIds($parentCriteria, $context)->getIds();
        $this->themeChildRepository->delete($toDeleteIds, $context);
        $this->themeChildRepository->upsert($parentThemes, $context);
    }

    public function removeTheme(string $technicalName, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('dependentThemes');
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));

        /** @var ThemeEntity|null $theme */
        $theme = $this->themeRepository->search($criteria, $context)->first();

        if ($theme === null) {
            return;
        }

        $dependentThemes = $theme->getDependentThemes() ?? new ThemeCollection();
        $ids = [...array_values($dependentThemes->getIds()), ...[$theme->getId()]];

        $this->removeOldMedia($technicalName, $context);
        $this->themeRepository->delete(array_map(fn (string $id) => ['id' => $id], $ids), $context);
    }

    private function getThemeByTechnicalName(string $technicalName, Context $context): ?ThemeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));
        $criteria->addAssociation('previewMedia');

        $theme = $this->themeRepository->search($criteria, $context)->first();

        return $theme instanceof ThemeEntity ? $theme : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function createMediaStruct(StorefrontPluginConfiguration $pluginConfig, string $path, string $mediaId, ?string $themeFolderId): ?array
    {
        $fs = $this->themeFilesystemResolver->getFilesystemForStorefrontConfig($pluginConfig);

        if (!$fs->hasFile('Resources', $path)) {
            return null;
        }

        $path = $fs->path('Resources', $path);
        $pathinfo = pathinfo($path);

        return [
            'basename' => $pathinfo['filename'],
            'media' => ['id' => $mediaId, 'mediaFolderId' => $themeFolderId],
            'mediaFile' => new MediaFile(
                $path,
                (string) MimeType::fromFilename($pathinfo['basename']),
                $pathinfo['extension'] ?? '',
                (int) filesize($path)
            ),
        ];
    }

    private function getMediaDefaultFolderId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', 'theme'));
        $criteria->setLimit(1);

        /** @var array<string> $defaultFolderIds */
        $defaultFolderIds = $this->mediaFolderRepository->searchIds($criteria, $context)->getIds();

        return \count($defaultFolderIds) === 1 ? $defaultFolderIds[0] : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getTranslationsConfiguration(StorefrontPluginConfiguration $configuration, Context $context): array
    {
        $systemLanguageLocale = $this->getSystemLanguageLocale($context);

        $themeConfig = $configuration->getThemeConfig();
        if (!$themeConfig) {
            return [];
        }

        $labelTranslations = $this->getLabelsFromConfig($themeConfig);
        $translations = $this->mapTranslations($labelTranslations, 'labels', $systemLanguageLocale);

        $helpTextTranslations = $this->getHelpTextsFromConfig($themeConfig);

        return array_merge_recursive(
            $translations,
            $this->mapTranslations($helpTextTranslations, 'helpTexts', $systemLanguageLocale)
        );
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, array<string, mixed>>
     */
    private function getLabelsFromConfig(array $config): array
    {
        $translations = [];
        if (\array_key_exists('blocks', $config)) {
            $translations = array_merge_recursive($translations, $this->extractLabels('blocks', $config['blocks']));
        }

        if (\array_key_exists('sections', $config)) {
            $translations = array_merge_recursive($translations, $this->extractLabels('sections', $config['sections']));
        }

        if (\array_key_exists('tabs', $config)) {
            $translations = array_merge_recursive($translations, $this->extractLabels('tabs', $config['tabs']));
        }

        if (\array_key_exists('fields', $config)) {
            $translations = array_merge_recursive($translations, $this->extractLabels('fields', $config['fields']));
        }

        return $translations;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, array<string, mixed>>
     */
    private function extractLabels(string $prefix, array $data): array
    {
        $labels = [];
        foreach ($data as $key => $item) {
            if (\array_key_exists('label', $item)) {
                /**
                 * @var string $locale
                 * @var string $label
                 */
                foreach ($item['label'] as $locale => $label) {
                    $labels[$locale][$prefix . '.' . $key] = $label;
                }
            }
        }

        return $labels;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, array<string, mixed>>
     */
    private function getHelpTextsFromConfig(array $config): array
    {
        $translations = [];

        if (\array_key_exists('fields', $config)) {
            $translations = array_merge_recursive($translations, $this->extractHelpTexts('fields', $config['fields']));
        }

        return $translations;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, array<string, mixed>>
     */
    private function extractHelpTexts(string $prefix, array $data): array
    {
        $helpTexts = [];
        foreach ($data as $key => $item) {
            if (!isset($item['helpText'])) {
                continue;
            }

            /**
             * @var string $locale
             * @var string $label
             */
            foreach ($item['helpText'] as $locale => $label) {
                $helpTexts[$locale][$prefix . '.' . $key] = $label;
            }
        }

        return $helpTexts;
    }

    private function removeOldMedia(string $technicalName, Context $context): void
    {
        $theme = $this->getThemeByTechnicalName($technicalName, $context);

        if (!$theme) {
            return;
        }

        // find all assigned media files
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media.themeMedia.id', $theme->getId()));
        $result = $this->mediaRepository->searchIds($criteria, $context);

        // delete theme media association
        $themeMediaData = [];
        foreach ($result->getIds() as $id) {
            $themeMediaData[] = ['themeId' => $theme->getId(), 'mediaId' => $id];
        }

        if (empty($themeMediaData)) {
            return;
        }

        // remove associations between theme and media first
        $this->themeMediaRepository->delete($themeMediaData, $context);

        // delete media associated with theme
        foreach ($themeMediaData as $item) {
            try {
                $this->mediaRepository->delete([['id' => $item['mediaId']]], $context);
            } catch (RestrictDeleteViolationException) {
                // don't delete files that are associated with other entities.
                // This files will be recreated using the file name strategy for duplicated filenames.
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function updateMediaInConfiguration(
        ?ThemeEntity $theme,
        StorefrontPluginConfiguration $pluginConfiguration,
        Context $context
    ): array {
        $media = [];
        $themeData = [];
        $themeFolderId = $this->getMediaDefaultFolderId($context);

        $installedConfiguration = null;
        if ($theme && \is_array($theme->getThemeJson())) {
            $fs = $this->themeFilesystemResolver->getFilesystemForStorefrontConfig($pluginConfiguration);

            $installedConfiguration = $this->pluginConfigurationFactory->createFromThemeJson(
                $theme->getTechnicalName() ?? 'childTheme',
                $theme->getThemeJson(),
                $fs->location,
            );
        }

        if (
            $pluginConfiguration->getPreviewMedia()
            && $pluginConfiguration->getPreviewMedia() !== $installedConfiguration?->getPreviewMedia()
            && (
                $theme === null
                || $theme->getPreviewMedia() === null
                || basename($installedConfiguration?->getPreviewMedia() ?? '') !== $theme->getPreviewMedia()->getFileNameIncludingExtension()
            )
        ) {
            $mediaId = Uuid::randomHex();

            $path = $pluginConfiguration->getPreviewMedia();

            $mediaItem = $this->createMediaStruct($pluginConfiguration, $path, $mediaId, $themeFolderId);

            if ($mediaItem) {
                $themeData['previewMediaId'] = $mediaId;
                $media[$path] = $mediaItem;
            }
        }

        $baseConfig = $pluginConfiguration->getThemeConfig() ?? [];
        $installedBaseConfig = $installedConfiguration?->getThemeConfig() ?? [];

        $currentThemeMedia = null;
        $currentMediaIds = null;
        $toDeleteIds = null;
        // get existing MediaFiles
        if ($theme !== null && \array_key_exists('fields', $theme->getBaseConfig() ?? [])) {
            foreach ($theme->getBaseConfig()['fields'] as $key => $field) {
                if ($this->hasOldMedia($field) === false) {
                    continue;
                }
                $currentMediaIds[$key] = $field['value'];
            }

            if (!empty($currentMediaIds)) {
                $currentThemeMedia = $this->mediaRepository->search(new Criteria($currentMediaIds), $context);
            }
        }

        if (\array_key_exists('fields', $baseConfig)) {
            foreach ($baseConfig['fields'] as $key => $field) {
                if ($this->hasNewMedia($field) === false) {
                    continue;
                }

                if (
                    isset($installedBaseConfig['fields'][$key]['value'])
                    && $field['value'] === $installedBaseConfig['fields'][$key]['value']
                ) {
                    $baseConfig['fields'][$key]['value'] = $currentMediaIds[$key] ?? $baseConfig['fields'][$key]['value'];

                    continue;
                }

                $path = $field['value'];

                if (!\array_key_exists($path, $media)) {
                    if (
                        $currentThemeMedia !== null
                        && !empty($currentMediaIds)
                        && isset($currentMediaIds[$key])
                        && $currentThemeMedia->getEntities()->get($currentMediaIds[$key])?->getFileNameIncludingExtension() === basename($path)) {
                        continue;
                    }

                    $criteriaMedia = new Criteria();
                    $criteriaMedia->addFilter(new EqualsFilter('fileName', basename($path)));
                    if ($this->mediaRepository->searchIds($criteriaMedia, $context)->getTotal() > 0) {
                        continue;
                    }

                    $mediaId = Uuid::randomHex();
                    $mediaItem = $this->createMediaStruct($pluginConfiguration, $path, $mediaId, $themeFolderId);

                    if (!$mediaItem) {
                        continue;
                    }

                    $media[$path] = $mediaItem;

                    // replace media path with media ids
                    $baseConfig['fields'][$key]['value'] = $mediaId;
                } else {
                    $baseConfig['fields'][$key]['value'] = $media[$path]['media']['id'];
                }
                if ($theme && isset($currentMediaIds[$key])) {
                    $toDeleteIds[] = $currentMediaIds[$key];
                }
            }
            $themeData['baseConfig'] = $baseConfig;
        }

        $mediaIds = [];

        if (!empty($media)) {
            $mediaIds = array_column($media, 'media');

            $this->mediaRepository->create($mediaIds, $context);

            foreach ($media as $item) {
                if (!$item['mediaFile'] instanceof MediaFile) {
                    throw MediaException::missingFile($item['media']['id']);
                }

                try {
                    $this->fileSaver->persistFileToMedia($item['mediaFile'], $item['basename'], $item['media']['id'], $context);
                } catch (MediaException $e) {
                    if ($e->getErrorCode() !== MediaException::MEDIA_DUPLICATED_FILE_NAME) {
                        throw $e;
                    }

                    $newFileName = $this->fileNameProvider->provide(
                        $item['basename'],
                        $item['mediaFile']->getFileExtension(),
                        null,
                        $context
                    );
                    $this->fileSaver->persistFileToMedia($item['mediaFile'], $newFileName, $item['media']['id'], $context);
                }
            }
        }

        $themeData['media'] = $mediaIds;

        if ($theme && \is_array($toDeleteIds)) {
            $toDeleteIds = array_unique($toDeleteIds);
            foreach ($toDeleteIds as $id) {
                if (Uuid::isValid($id)) {
                    $themeData['toDeleteMedia'][] = [
                        'mediaId' => $id,
                        'themeId' => $theme->getId(),
                    ];
                }
            }
        }

        return $themeData;
    }

    private function getSystemLanguageLocale(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addAssociation('translationCode');
        $criteria->addFilter(new EqualsFilter('id', Defaults::LANGUAGE_SYSTEM));

        /** @var LanguageEntity $language */
        $language = $this->languageRepository->search($criteria, $context)->first();
        /** @var LocaleEntity $locale */
        $locale = $language->getTranslationCode();

        return $locale->getCode();
    }

    /**
     * @param array<string, mixed> $translations
     *
     * @return array<string, array<string, mixed>>
     */
    private function mapTranslations(array $translations, string $property, string $systemLanguageLocale): array
    {
        $result = [];
        $containsSystemLanguage = false;
        foreach ($translations as $locale => $translation) {
            if ($locale === $systemLanguageLocale) {
                $containsSystemLanguage = true;
            }
            $result[$locale] = [$property => $translation];
        }

        if (!$containsSystemLanguage && \count($translations) > 0) {
            $translation = array_shift($translations);
            if (\array_key_exists('en-GB', $translations)) {
                $translation = $translations['en-GB'];
            }
            $result[$systemLanguageLocale] = [$property => $translation];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $themeData
     *
     * @return array<string, mixed>
     */
    private function addParentTheme(StorefrontPluginConfiguration $configuration, array $themeData, Context $context): array
    {
        $lastNotSameTheme = null;
        foreach (array_reverse($configuration->getConfigInheritance()) as $themeName) {
            if (
                $themeName === '@' . StorefrontPluginRegistry::BASE_THEME_NAME
                || $themeName === '@' . $themeData['technicalName']
            ) {
                continue;
            }
            /** @var string $lastNotSameTheme */
            $lastNotSameTheme = str_replace('@', '', (string) $themeName);
        }

        if ($lastNotSameTheme !== null) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('technicalName', $lastNotSameTheme));

            $parentThemeId = $this->themeRepository->searchIds($criteria, $context)->firstId();

            if ($parentThemeId) {
                $themeData['parentThemeId'] = $parentThemeId;
            }
        }

        return $themeData;
    }

    /**
     * @return list<array{parentId: string, childId: string}>
     */
    private function getParentThemes(StorefrontPluginConfiguration $config, string $id): array
    {
        $allThemeConfigs = $this->pluginRegistry->getConfigurations()->getThemes();

        $allThemes = $this->getAllThemesPlain();

        $parentThemeConfigs = $allThemeConfigs->filter(
            fn (StorefrontPluginConfiguration $parentConfig) => $this->isDependentTheme($parentConfig, $config)
        );

        $technicalNames = $parentThemeConfigs->map(
            fn (StorefrontPluginConfiguration $theme) => $theme->getTechnicalName()
        );

        $parentThemes = array_filter(
            $allThemes,
            fn (array $theme) => \in_array($theme['technicalName'], $technicalNames, true)
        );

        $updateParents = [];
        foreach ($parentThemes as $parentTheme) {
            $updateParents[] = [
                'parentId' => $parentTheme['parentThemeId'],
                'childId' => $id,
            ];
        }

        return $updateParents;
    }

    /**
     * @return list<array{technicalName: string, parentThemeId: string}>
     */
    private function getAllThemesPlain(): array
    {
        /** @var list<array{technicalName: string, parentThemeId: string}> $result */
        $result = $this->connection->fetchAllAssociative(
            'SELECT theme.technical_name as technicalName, LOWER(HEX(theme.id)) as parentThemeId FROM theme'
        );

        return $result;
    }

    private function isDependentTheme(
        StorefrontPluginConfiguration $parentConfig,
        StorefrontPluginConfiguration $currentThemeConfig
    ): bool {
        return $currentThemeConfig->getTechnicalName() !== $parentConfig->getTechnicalName()
            && \in_array('@' . $parentConfig->getTechnicalName(), $currentThemeConfig->getStyleFiles()->getFilepaths(), true)
        ;
    }

    /**
     * @param array<int|string, mixed> $field
     */
    private function hasNewMedia(array $field): bool
    {
        return \array_key_exists('type', $field) && $field['type'] === 'media'
            && \array_key_exists('value', $field) && \is_string($field['value']);
    }

    /**
     * @param array<int|string, mixed> $field
     */
    private function hasOldMedia(array $field): bool
    {
        return \array_key_exists('type', $field) && $field['type'] === 'media'
            && \array_key_exists('value', $field) && \is_string($field['value']) && Uuid::isValid($field['value']);
    }
}
