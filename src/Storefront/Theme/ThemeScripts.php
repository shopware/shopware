<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

#[Package('storefront')]
class ThemeScripts
{
    /**
     * @internal
     */
    public function __construct(
        private readonly StorefrontPluginRegistryInterface $pluginRegistry,
        private readonly ThemeFileResolver $themeFileResolver,
        private readonly EntityRepository $themeRepository,
        private readonly AbstractThemePathBuilder $themePathBuilder,
        private readonly FilesystemOperator $themeFilesystem,
        private readonly ThemeFileImporterInterface $themeFileImporter,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function getThemeScripts(SalesChannelContext $context, ?string $themeId): array
    {
        if ($themeId === null) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->setTitle('theme-scripts::load-themes');
        $themes = $this->themeRepository->search($criteria, $context->getContext())->getEntities();

        /** @var ThemeEntity $theme */
        $theme = $themes->get($themeId);

        // if theme does not have a technical name, it is inherited from parent theme (db themes)
        if ($theme && $theme->getTechnicalName() === null && $theme->getParentThemeId() !== null) {
            /** @var ThemeEntity $theme */
            $theme = $themes->get($theme->getParentThemeId());
        }

        if ($theme === null) {
            return [];
        }

        $themeConfig = $this->pluginRegistry->getConfigurations()->getByTechnicalName(
            $theme->getTechnicalName() ?? ''
        );
        if ($themeConfig === null) {
            return [];
        }

        $themePath = $this->getThemePath($context, $themeId);
        if ($themePath === null) {
            return [];
        }

        $subFolders = $this->readThemeSubFolders($themePath);
        if ($subFolders === null) {
            return [];
        }

        $resolvedFiles = $this->themeFileResolver->resolveFiles(
            $themeConfig,
            $this->pluginRegistry->getConfigurations(),
            false
        );

        $mainEntryFiles = $this->getMainEntryFiles($resolvedFiles, $subFolders);

        return $this->validateMainEntryFiles($mainEntryFiles, $themePath);
    }

    private function getThemePath(SalesChannelContext $context, string $themeId): ?string
    {
        $salesChannel = $context->getSalesChannel();

        $themePrefix = $this->themePathBuilder->assemblePath($salesChannel->getId(), $themeId);
        if (!$themePrefix) {
            return null;
        }

        $themePath = 'theme' . \DIRECTORY_SEPARATOR . $themePrefix;
        $themePath = $this->themeFileImporter->getRealPath($themePath);

        return !$this->themeFilesystem->has($themePath) ? null : $themePath;
    }

    private function readThemeSubFolders(string $themePath): ?Finder
    {
        try {
            $finder = (new Finder())->directories()->in($themePath)->exclude(['css', 'js/storefront/js']);
        } catch (DirectoryNotFoundException $e) {
            $this->logger->error($e->getMessage());
        }

        return $finder ?? null;
    }

    /**
     * @param array<string, FileCollection> $resolvedFiles
     *
     * @return array<int, string>
     */
    private function getMainEntryFiles(array $resolvedFiles, Finder $subFolders): array
    {
        $mainEntryFiles = [];
        if (isset($resolvedFiles[ThemeFileResolver::SCRIPT_FILES])) {
            $scriptsFileCollection = $resolvedFiles[ThemeFileResolver::SCRIPT_FILES];
            foreach ($scriptsFileCollection as $scriptFile) {
                $name = basename($scriptFile->getFilepath(), '.js');
                foreach ($subFolders as $subFolder) {
                    $realPath = $subFolder->getRealPath();
                    $folderPath = 'js' . \DIRECTORY_SEPARATOR . $name;
                    if (\is_string($realPath) && str_contains($realPath, $folderPath)) {
                        $mainEntryFiles[] = $folderPath . \DIRECTORY_SEPARATOR . $name . '.js';
                    }
                }
            }
        }

        return array_unique($mainEntryFiles);
    }

    /**
     * @param array<int, string> $entryFiles
     *
     * @return array<int, string>
     */
    private function validateMainEntryFiles(array $entryFiles, string $themePath): array
    {
        $mainEntryFiles = [];
        foreach ($entryFiles as $entryFile) {
            $path = $themePath . \DIRECTORY_SEPARATOR . $entryFile;
            if ($this->themeFilesystem->has($path)) {
                $mainEntryFiles[] = $entryFile;
            }
        }

        return $mainEntryFiles;
    }
}
