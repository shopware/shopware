<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Composer\IO\IOInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Changelog\ChangelogService;
use Shopware\Core\Framework\Plugin\Exception\ExceptionCollection;
use Shopware\Core\Framework\Plugin\Exception\PluginChangelogInvalidException;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Plugin\Util\VersionSanitizer;
use Shopware\Core\System\Language\LanguageEntity;

class PluginService
{
    /**
     * @var string
     */
    private $pluginDir;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepo;

    /**
     * @var ChangelogService
     */
    private $changelogService;

    /**
     * @var PluginFinder
     */
    private $pluginFinder;

    /**
     * @var VersionSanitizer
     */
    private $versionSanitizer;

    public function __construct(
        string $pluginDir,
        string $projectDir,
        EntityRepositoryInterface $pluginRepo,
        EntityRepositoryInterface $languageRepo,
        ChangelogService $changelogService,
        PluginFinder $pluginFinder,
        VersionSanitizer $versionSanitizer
    ) {
        $this->pluginDir = $pluginDir;
        $this->projectDir = $projectDir;
        $this->pluginRepo = $pluginRepo;
        $this->languageRepo = $languageRepo;
        $this->changelogService = $changelogService;
        $this->pluginFinder = $pluginFinder;
        $this->versionSanitizer = $versionSanitizer;
    }

    public function refreshPlugins(Context $shopwareContext, IOInterface $composerIO): ExceptionCollection
    {
        $errors = new ExceptionCollection();
        $pluginsFromFileSystem = $this->pluginFinder->findPlugins($this->pluginDir, $this->projectDir, $errors, $composerIO);

        $installedPlugins = $this->getPlugins(new Criteria(), $shopwareContext);

        $plugins = [];
        foreach ($pluginsFromFileSystem as $pluginFromFileSystem) {
            $baseClass = $pluginFromFileSystem->getBaseClass();
            $pluginPath = $pluginFromFileSystem->getPath();
            $info = $pluginFromFileSystem->getComposerPackage();

            $autoload = $info->getAutoload();
            if (empty($autoload) || (empty($autoload['psr-4']) && empty($autoload['psr-0']))) {
                $errors->add(new PluginComposerJsonInvalidException(
                    $pluginPath . '/composer.json',
                    ['Neither a PSR-4 nor PSR-0 autoload information is given.']
                ));

                continue;
            }

            $pluginVersion = $this->versionSanitizer->sanitizePluginVersion($info->getVersion());
            $extra = $info->getExtra();

            $authors = null;
            $composerAuthors = $info->getAuthors();
            if ($composerAuthors !== null) {
                $authorNames = array_column($info->getAuthors(), 'name');
                $authors = implode(', ', $authorNames);
            }
            $license = $info->getLicense();
            $pluginIconPath = $extra['plugin-icon'] ?? 'src/Resources/config/plugin.png';

            $pluginData = [
                'name' => $pluginFromFileSystem->getName(),
                'baseClass' => $baseClass,
                'composerName' => $info->getName(),
                'path' => str_replace($this->projectDir . '/', '', $pluginPath),
                'author' => $authors,
                'copyright' => $extra['copyright'] ?? null,
                'license' => implode(', ', $license),
                'version' => $pluginVersion,
                'iconRaw' => $this->getPluginIconRaw($pluginPath . '/' . $pluginIconPath),
                'autoload' => $info->getAutoload(),
                'managedByComposer' => $pluginFromFileSystem->getManagedByComposer(),
            ];

            $translatableExtraKeys = ['label', 'description', 'manufacturerLink', 'supportLink'];
            foreach ($extra as $extraKey => $extraItem) {
                if (\in_array($extraKey, $translatableExtraKeys, true)) {
                    foreach ($extraItem as $locale => $translation) {
                        $languageId = $this->getLanguageIdForLocale($locale, $shopwareContext);
                        if ($languageId === '') {
                            continue;
                        }
                        $pluginData['translations'][$languageId][$extraKey] = $translation;
                    }
                }
            }

            if ($changelogFiles = $this->changelogService->getChangelogFiles($pluginPath)) {
                foreach ($changelogFiles as $file) {
                    $languageId = $this->getLanguageIdForLocale(
                        $this->changelogService->getLocaleFromChangelogFile($file),
                        $shopwareContext
                    );

                    try {
                        $pluginData['translations'][$languageId]['changelog'] = $this->changelogService->parseChangelog($file);
                    } catch (PluginChangelogInvalidException $e) {
                        $errors->add($e);
                    }
                }
            }

            /** @var PluginEntity $currentPluginEntity */
            $currentPluginEntity = $installedPlugins->filterByProperty('baseClass', $baseClass)->first();
            if ($currentPluginEntity !== null) {
                $currentPluginId = $currentPluginEntity->getId();
                $pluginData['id'] = $currentPluginId;

                $currentPluginVersion = $currentPluginEntity->getVersion();
                if ($this->hasPluginUpdate($pluginVersion, $currentPluginVersion)) {
                    $pluginData['version'] = $currentPluginVersion;
                    $pluginData['upgradeVersion'] = $pluginVersion;
                } else {
                    $pluginData['upgradeVersion'] = null;
                }

                $installedPlugins->remove($currentPluginId);
            }

            $plugins[] = $pluginData;
        }

        if ($plugins) {
            $this->pluginRepo->upsert($plugins, $shopwareContext);
        }

        // delete plugins, which are in storage but not in filesystem anymore
        $deletePluginIds = $installedPlugins->getIds();
        if (\count($deletePluginIds) !== 0) {
            $deletePlugins = [];
            foreach ($deletePluginIds as $deletePluginId) {
                $deletePlugins[] = ['id' => $deletePluginId];
            }
            $this->pluginRepo->delete($deletePlugins, $shopwareContext);
        }

        return $errors;
    }

    /**
     * @throws PluginNotFoundException
     */
    public function getPluginByName(string $pluginName, Context $context): PluginEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $pluginName));

        $pluginEntity = $this->getPlugins($criteria, $context)->first();
        if ($pluginEntity === null) {
            throw new PluginNotFoundException($pluginName);
        }

        return $pluginEntity;
    }

    private function getPlugins(Criteria $criteria, Context $context): PluginCollection
    {
        /** @var PluginCollection $pluginCollection */
        $pluginCollection = $this->pluginRepo->search($criteria, $context)->getEntities();

        return $pluginCollection;
    }

    private function hasPluginUpdate(string $updateVersion, string $currentVersion): bool
    {
        return version_compare($updateVersion, $currentVersion, '>');
    }

    private function getLanguageIdForLocale(string $locale, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('language.translationCode.code', $locale));
        $result = $this->languageRepo->search($criteria, $context);

        if ($result->getTotal() === 0) {
            return '';
        }

        /** @var LanguageEntity $languageEntity */
        $languageEntity = $result->first();

        return $languageEntity->getId();
    }

    private function getPluginIconRaw(string $pluginIconPath): ?string
    {
        if (!is_file($pluginIconPath)) {
            return null;
        }

        return file_get_contents($pluginIconPath);
    }
}
