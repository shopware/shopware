<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Composer\IO\IOInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\Helper\ComposerPackageProvider;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Component\Finder\Finder;

class PluginService
{
    /**
     * @var string
     */
    private $pluginPath;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepo;

    /**
     * @var ComposerPackageProvider
     */
    private $composerPackageProvider;

    public function __construct(
        string $pluginPath,
        EntityRepositoryInterface $pluginRepo,
        EntityRepositoryInterface $languageRepo,
        ComposerPackageProvider $composerPackageProvider
    ) {
        $this->pluginPath = $pluginPath;
        $this->pluginRepo = $pluginRepo;
        $this->languageRepo = $languageRepo;
        $this->composerPackageProvider = $composerPackageProvider;
    }

    /**
     * @return PluginComposerJsonInvalidException[]
     */
    public function refreshPlugins(Context $shopwareContext, IOInterface $composerIO): array
    {
        $finder = new Finder();
        $filesystemPlugins = $finder->directories()->depth(0)->in($this->pluginPath)->getIterator();

        $installedPlugins = $this->getPlugins(new Criteria(), $shopwareContext);

        $plugins = [];
        $errors = [];
        foreach ($filesystemPlugins as $plugin) {
            $pluginName = $plugin->getFilename();
            $pluginPath = $plugin->getPathname();

            try {
                $info = $this->composerPackageProvider->getPluginInformation($pluginPath, $composerIO);
            } catch (PluginComposerJsonInvalidException $e) {
                $errors[] = $e;
                continue;
            }

            $pluginVersion = $info->getVersion();
            /** @var array $extra */
            $extra = $info->getExtra();

            $authors = null;
            $composerAuthors = $info->getAuthors();
            if ($composerAuthors !== null) {
                $authorNames = array_column($info->getAuthors(), 'name');
                $authors = implode(', ', $authorNames);
            }
            $license = $info->getLicense();

            $pluginData = [
                'name' => $pluginName,
                'author' => $authors,
                'copyright' => $extra['copyright'] ?? null,
                'license' => implode(', ', $license),
                'version' => $pluginVersion,
            ];

            $pluginData = $this->getTranslation($extra, $pluginData, 'label', 'label', $shopwareContext);
            $pluginData = $this->getTranslation($extra, $pluginData, 'description', 'description', $shopwareContext);
            $pluginData = $this->getTranslation($extra, $pluginData, 'manufacturerLink', 'manufacturerLink', $shopwareContext);
            $pluginData = $this->getTranslation($extra, $pluginData, 'supportLink', 'supportLink', $shopwareContext);

            /** @var PluginEntity $currentPluginEntity */
            $currentPluginEntity = $installedPlugins->filterByProperty('name', $pluginName)->first();
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

    private function getTranslation(
        array $composerExtra,
        array $pluginData,
        string $composerProperty,
        string $pluginField,
        Context $shopwareContext
    ): array {
        foreach ($composerExtra[$composerProperty] ?? [] as $locale => $labelTranslation) {
            $languageId = $this->getLanguageIdForLocale($locale, $shopwareContext);
            if ($languageId === '') {
                continue;
            }

            $pluginData['translations'][$languageId][$pluginField] = $labelTranslation;
        }

        return $pluginData;
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
}
