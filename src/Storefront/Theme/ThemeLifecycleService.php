<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Content\Media\Exception\DuplicatedMediaFileNameException;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use function GuzzleHttp\Psr7\mimetype_from_filename;

class ThemeLifecycleService
{
    /**
     * @var StorefrontPluginRegistryInterface
     */
    private $pluginRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $themeRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaFolderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $themeMediaRepository;

    /**
     * @var FileSaver
     */
    private $fileSaver;

    /**
     * @var ThemeFileImporterInterface
     */
    private $themeFileImporter;

    /**
     * @var FileNameProvider
     */
    private $fileNameProvider;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    public function __construct(
        StorefrontPluginRegistryInterface $pluginRegistry,
        EntityRepositoryInterface $themeRepository,
        EntityRepositoryInterface $mediaRepository,
        EntityRepositoryInterface $mediaFolderRepository,
        EntityRepositoryInterface $themeMediaRepository,
        FileSaver $fileSaver,
        FileNameProvider $fileNameProvider,
        ThemeFileImporterInterface $themeFileImporter,
        EntityRepositoryInterface $languageRepository
    ) {
        $this->pluginRegistry = $pluginRegistry;
        $this->themeRepository = $themeRepository;
        $this->mediaRepository = $mediaRepository;
        $this->mediaFolderRepository = $mediaFolderRepository;
        $this->themeMediaRepository = $themeMediaRepository;
        $this->fileSaver = $fileSaver;
        $this->fileNameProvider = $fileNameProvider;
        $this->themeFileImporter = $themeFileImporter;
        $this->languageRepository = $languageRepository;
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
        $themeData['name'] = $configuration->getName();
        $themeData['technicalName'] = $configuration->getTechnicalName();
        $themeData['author'] = $configuration->getAuthor();

        $this->removeOldMedia($configuration->getTechnicalName(), $context);

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

        $this->themeRepository->upsert([$themeData], $context);
    }

    public function removeTheme(string $technicalName, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('childThemes');
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));

        $theme = $this->themeRepository->search($criteria, $context)->first();

        if ($theme === null) {
            return;
        }

        $ids = array_merge(array_values($theme->getChildThemes()->getIds()), [$theme->getId()]);

        $this->removeOldMedia($technicalName, $context);
        $this->themeRepository->delete(array_map(function (string $id) {
            return ['id' => $id];
        }, $ids), $context);
    }

    private function getThemeByTechnicalName(string $technicalName, Context $context): ?ThemeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));

        return $this->themeRepository->search($criteria, $context)->first();
    }

    private function createMediaStruct(string $path, string $mediaId, ?string $themeFolderId): ?array
    {
        if (!$this->fileExists($path)) {
            return null;
        }

        $path = $this->themeFileImporter->getRealPath($path);

        $pathinfo = pathinfo($path);

        return [
            'basename' => $pathinfo['filename'],
            'media' => ['id' => $mediaId, 'mediaFolderId' => $themeFolderId],
            'mediaFile' => new MediaFile(
                $path,
                mimetype_from_filename($pathinfo['basename']),
                $pathinfo['extension'],
                filesize($path)
            ),
        ];
    }

    private function getMediaDefaultFolderId(string $folder, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', $folder));
        $criteria->addAssociation('defaultFolder');
        $criteria->setLimit(1);
        $defaultFolder = $this->mediaFolderRepository->search($criteria, $context);
        $defaultFolderId = null;
        if ($defaultFolder->count() === 1) {
            $defaultFolderId = $defaultFolder->first()->getId();
        }

        return $defaultFolderId;
    }

    private function getTranslationsConfiguration(StorefrontPluginConfiguration $configuration, Context $context): array
    {
        $systemLanguageLocale = $this->getSystemLanguageLocale($context);

        $labelTranslations = $this->getLabelsFromConfig($configuration->getThemeConfig());
        $translations = $this->mapTranslations($labelTranslations, 'labels', $systemLanguageLocale);

        $helpTextTranslations = $this->getHelpTextsFromConfig($configuration->getThemeConfig());

        return array_merge_recursive(
            $translations,
            $this->mapTranslations($helpTextTranslations, 'helpTexts', $systemLanguageLocale)
        );
    }

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

    private function extractLabels(string $prefix, array $data): array
    {
        $labels = [];
        foreach ($data as $key => $item) {
            if (\array_key_exists('label', $item)) {
                foreach ($item['label'] as $locale => $label) {
                    $labels[$locale][$prefix . '.' . $key] = $label;
                }
            }
        }

        return $labels;
    }

    private function getHelpTextsFromConfig(array $config): array
    {
        $translations = [];

        if (\array_key_exists('fields', $config)) {
            $translations = array_merge_recursive($translations, $this->extractHelpTexts('fields', $config['fields']));
        }

        return $translations;
    }

    private function extractHelpTexts(string $prefix, array $data): array
    {
        $helpTexts = [];
        foreach ($data as $key => $item) {
            if (!isset($item['helpText'])) {
                continue;
            }

            foreach ($item['helpText'] as $locale => $label) {
                $helpTexts[$locale][$prefix . '.' . $key] = $label;
            }
        }

        return $helpTexts;
    }

    private function fileExists(string $path): bool
    {
        return $this->themeFileImporter->fileExists($path);
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
            } catch (RestrictDeleteViolationException $e) {
                // don't delete files that are associated with other entities.
                // This files will be recreated using the file name strategy for duplicated filenames.
            }
        }
    }

    private function updateMediaInConfiguration(?ThemeEntity $theme, StorefrontPluginConfiguration $pluginConfiguration, Context $context): array
    {
        $media = [];
        $themeData = [];
        $themeFolderId = $this->getMediaDefaultFolderId('theme', $context);

        if ($pluginConfiguration->getPreviewMedia()) {
            $mediaId = Uuid::randomHex();
            $path = $pluginConfiguration->getPreviewMedia();

            $mediaItem = $this->createMediaStruct($path, $mediaId, $themeFolderId);

            if ($mediaItem) {
                $themeData['previewMediaId'] = $mediaId;
                $media[$path] = $mediaItem;
            }

            // if preview was not deleted because it is not created from theme use current preview id
            if ($theme && $theme->getPreviewMediaId() !== null) {
                $themeData['previewMediaId'] = $theme->getPreviewMediaId();
            }
        }

        $baseConfig = $pluginConfiguration->getThemeConfig();

        if (\array_key_exists('fields', $baseConfig)) {
            foreach ($baseConfig['fields'] as $key => $field) {
                if (!\array_key_exists('type', $field) || $field['type'] !== 'media') {
                    continue;
                }

                $path = $pluginConfiguration->getBasePath() . \DIRECTORY_SEPARATOR . $field['value'];

                if (!\array_key_exists($path, $media)) {
                    $mediaId = Uuid::randomHex();
                    $mediaItem = $this->createMediaStruct($path, $mediaId, $themeFolderId);

                    if (!$mediaItem) {
                        continue;
                    }

                    $media[$path] = $mediaItem;

                    // replace media path with media ids
                    $baseConfig['fields'][$key]['value'] = $mediaId;
                } else {
                    $baseConfig['fields'][$key]['value'] = $media[$path]['media']['id'];
                }
            }
            $themeData['baseConfig'] = $baseConfig;
        }

        $mediaIds = [];

        if (!empty($media)) {
            $mediaIds = array_column($media, 'media');

            $this->mediaRepository->create($mediaIds, $context);

            foreach ($media as $item) {
                try {
                    $this->fileSaver->persistFileToMedia($item['mediaFile'], $item['basename'], $item['media']['id'], $context);
                } catch (DuplicatedMediaFileNameException $e) {
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

        return $themeData;
    }

    private function getSystemLanguageLocale(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addAssociation('translationCode');
        $criteria->addFilter(new EqualsFilter('id', Defaults::LANGUAGE_SYSTEM));

        /** @var LanguageEntity $language */
        $language = $this->languageRepository->search($criteria, $context)->first();

        return $language->getTranslationCode()->getCode();
    }

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
}
