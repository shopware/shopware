<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\ConfigLoader;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;

#[Package('storefront')]
class DatabaseRuntimeConfigLoader
{
    /**
     * @internal
     *
     * @param EntityRepository<ThemeCollection> $themeRepository
     */
    public function __construct(
        private readonly EntityRepository $themeRepository,
        private readonly Connection $connection,
    ) {
    }

    // todo: cache in local variable, check if arrays are acceptable/or should introduce a new class
    public function getThemes() {
        return $this->connection->fetchAllAssociative('SELECT HEX(id) AS `id`, technical_name FROM theme WHERE active = 1');
    }

    // todo: cache or add registry/load all active themes in one run
    public function loadById(string $themeId, Context $context): ?StorefrontPluginConfiguration
    {
        /** @var ThemeEntity|null $theme */
        $theme = $this->themeRepository
            ->search(new Criteria([$themeId]), $context)
            ->get($themeId);

        if ($theme === null) {
            return null;
        }


        return $this->themeToConfig($theme);
    }

    public function loadByTechnicalName(string $technicalName, Context $context): ?StorefrontPluginConfiguration
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));

        /** @var ThemeEntity|null $theme */
        $theme = $this->themeRepository
            ->search($criteria, $context)
            ->first();

        if ($theme === null) {
            return null;
        }


        return $this->themeToConfig($theme);
    }

    private function themeToConfig(ThemeEntity $theme): StorefrontPluginConfiguration
    {
        $data = $theme->getThemeJson();

        $config = new StorefrontPluginConfiguration($theme->getTechnicalName());

        $config->setThemeJson($data);
        // todo: check where we can get the path from
//        $config->setStorefrontEntryFilepath($this->getEntryFile($path));
        $config->setIsTheme(true);
        $config->setName($data['name']);
        $config->setAuthor($data['author']);

        if (\array_key_exists('style', $data) && \is_array($data['style'])) {
            $this->resolveStyleFiles($data['style'], $config);
        }

        if (\array_key_exists('script', $data) && \is_array($data['script'])) {
            $fileCollection = FileCollection::createFromArray($data['script']);
            $config->setScriptFiles($fileCollection);
        }

        if (\array_key_exists('asset', $data)) {
            $config->setAssetPaths($data['asset']);
        }

        if (\array_key_exists('previewMedia', $data)) {
            $config->setPreviewMedia($data['previewMedia']);
        }

        if (\array_key_exists('config', $data)) {
            $config->setThemeConfig($data['config']); // can use $theme->getBaseConfig()
        }

        if (\array_key_exists('views', $data)) {
            $config->setViewInheritance($data['views']);
        }

        if (\array_key_exists('configInheritance', $data)) {
            $config->setConfigInheritance($data['configInheritance']);
            $baseConfig = $config->getThemeConfig();
            $baseConfig['configInheritance'] = $data['configInheritance'];
            $config->setThemeConfig($baseConfig);
        }

        if (\array_key_exists('iconSets', $data)) {
            $config->setIconSets($data['iconSets']);
        }

        return $config;
    }


    /**
     * todo: replace duplication with extracting to a separate class
     *
     * @param array<string|array<array{resolve?: array<string, string>}>> $styles
     */
    private function resolveStyleFiles(array $styles, StorefrontPluginConfiguration $config): void
    {
        $fileCollection = new FileCollection();
        foreach ($styles as $style) {
            if (!\is_array($style)) {
                $fileCollection->add(new File($style));

                continue;
            }

            foreach ($style as $filename => $additional) {
                if (!\array_key_exists('resolve', $additional)) {
                    $fileCollection->add(new File($filename));

                    continue;
                }

                $fileCollection->add(new File($filename, $additional['resolve'] ?? []));
            }
        }
        $config->setStyleFiles($fileCollection);
    }
}
