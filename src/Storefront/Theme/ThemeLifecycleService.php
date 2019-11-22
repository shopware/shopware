<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use function GuzzleHttp\Psr7\mimetype_from_filename;

class ThemeLifecycleService
{
    /**
     * @var StorefrontPluginRegistry
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

    public function __construct(
        StorefrontPluginRegistry $pluginRegistry,
        EntityRepositoryInterface $themeRepository,
        EntityRepositoryInterface $mediaRepository,
        EntityRepositoryInterface $mediaFolderRepository,
        EntityRepositoryInterface $themeMediaRepository,
        FileSaver $fileSaver
    ) {
        $this->pluginRegistry = $pluginRegistry;
        $this->themeRepository = $themeRepository;
        $this->mediaRepository = $mediaRepository;
        $this->mediaFolderRepository = $mediaFolderRepository;
        $this->themeMediaRepository = $themeMediaRepository;
        $this->fileSaver = $fileSaver;
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
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $configuration->getTechnicalName()));
        /** @var ThemeEntity|null $theme */
        $theme = $this->themeRepository->search($criteria, $context)->first();

        // check if theme config already exists in the database
        if ($theme) {
            $themeData['id'] = $theme->getId();

            // find all assigned media files
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('media.themeMedia.id', $theme->getId()));
            $result = $this->mediaRepository->searchIds($criteria, $context);
            $themeMediaData = [];

            // delete theme media association
            if ($result->getTotal() !== 0) {
                foreach ($result->getIds() as $id) {
                    $themeMediaData[] = ['themeId' => $theme->getId(), 'mediaId' => $id];
                }
            }

            if ($theme->getPreviewMediaId()) {
                $themeMediaData[] = ['themeId' => $theme->getId(), 'mediaId' => $theme->getPreviewMediaId()];
            }

            if (count($themeMediaData) > 0) {
                $this->themeMediaRepository->delete($themeMediaData, $context);
            }

            $mediaData = [];

            // delete media entities
            foreach ($themeMediaData as $item) {
                $mediaData[] = ['id' => $item['mediaId']];
            }

            if (count($mediaData) > 0) {
                $this->mediaRepository->delete($mediaData, $context);
            }
        } else {
            $themeData['active'] = true;
        }

        $translations = [];

        $labelTranslations = $this->getLabelsFromConfig($configuration->getThemeConfig());
        foreach ($labelTranslations as $locale => $translation) {
            $translations[$locale] = ['labels' => $translation];
        }

        $helpTextTranslations = $this->getHelpTextsFromConfig($configuration->getThemeConfig());
        foreach ($helpTextTranslations as $locale => $translation) {
            $translations[$locale]['helpTexts'] = $translation;
        }

        $themeData['name'] = $configuration->getName();
        $themeData['technicalName'] = $configuration->getTechnicalName();
        $themeData['author'] = $configuration->getAuthor();
        $themeData['translations'] = $translations;

        // handle media
        $themeFolderId = $this->getMediaDefaultFolderId('theme', $context);
        $media = [];

        if ($configuration->getPreviewMedia()) {
            $mediaId = Uuid::randomHex();
            $path = $configuration->getPreviewMedia();

            $mediaItem = $this->createMediaStruct($path, $mediaId, $themeFolderId);

            if ($mediaItem) {
                $themeData['previewMediaId'] = $mediaId;
                $media[$path] = $mediaItem;
            }
        }

        if (array_key_exists('fields', $configuration->getThemeConfig())) {
            $config = $configuration->getThemeConfig();

            foreach ($config['fields'] as $key => $field) {
                if ($field['type'] !== 'media') {
                    continue;
                }

                $path = $configuration->getBasePath() . DIRECTORY_SEPARATOR . $field['value'];

                if (!array_key_exists($path, $media)) {
                    $mediaId = Uuid::randomHex();
                    $mediaItem = $this->createMediaStruct($path, $mediaId, $themeFolderId);

                    if (!$mediaItem) {
                        continue;
                    }

                    $media[$path] = $mediaItem;

                    // replace media path with media ids
                    $config['fields'][$key]['value'] = $mediaId;
                } else {
                    $config['fields'][$key]['value'] = $media[$path]['media']['id'];
                }
            }
            $themeData['baseConfig'] = $config;
        }
        $themeData['media'] = array_column($media, 'media');

        $this->themeRepository->upsert([$themeData], $context);

        if (!empty($media)) {
            foreach ($media as $item) {
                $this->fileSaver->persistFileToMedia($item['mediaFile'], $item['basename'], $item['media']['id'], $context);
            }
        }
    }

    private function createMediaStruct(string $path, string $mediaId, string $themeFolderId): ?array
    {
        if (!file_exists($path) || is_dir($path)) {
            return null;
        }

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

    private function getLabelsFromConfig(array $config): array
    {
        $translations = [];
        if (array_key_exists('blocks', $config)) {
            $translations = array_merge_recursive($translations, $this->extractLabels('blocks', $config['blocks']));
        }

        if (array_key_exists('sections', $config)) {
            $translations = array_merge_recursive($translations, $this->extractLabels('sections', $config['sections']));
        }

        if (array_key_exists('fields', $config)) {
            $translations = array_merge_recursive($translations, $this->extractLabels('fields', $config['fields']));
        }

        return $translations;
    }

    private function extractLabels(string $prefix, array $data): array
    {
        $labels = [];
        foreach ($data as $key => $item) {
            foreach ($item['label'] as $locale => $label) {
                $labels[$locale][$prefix . '.' . $key] = $label;
            }
        }

        return $labels;
    }

    private function getHelpTextsFromConfig(array $config): array
    {
        $translations = [];

        if (array_key_exists('fields', $config)) {
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
}
