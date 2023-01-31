<?php
declare(strict_types=1);

namespace Shopware\Storefront\Theme\ConfigLoader;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use function sprintf;
use const JSON_THROW_ON_ERROR;

#[Package('storefront')]
class StaticFileConfigLoader extends AbstractConfigLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }

    public function getDecorated(): AbstractConfigLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $themeId, Context $context): StorefrontPluginConfiguration
    {
        $path = sprintf('theme-config/%s.json', $themeId);

        if (!$this->filesystem->fileExists($path)) {
            throw new \RuntimeException('Cannot find theme configuration. Did you run bin/console theme:dump');
        }

        $fileContent = $this->filesystem->read($path);
        \assert(\is_string($fileContent));
        $fileObject = json_decode($fileContent, true, 512, JSON_THROW_ON_ERROR);

        $fileObject = $this->prepareCollections($fileObject);

        $config = new StorefrontPluginConfiguration('');
        $config->assign($fileObject);

        return $config;
    }

    private function prepareCollections(array $fileObject): array
    {
        $fileObject['styleFiles'] = array_map(fn (array $file) => (new File(''))->assign($file), $fileObject['styleFiles']);

        $fileObject['scriptFiles'] = array_map(fn (array $file) => (new File(''))->assign($file), $fileObject['scriptFiles']);

        $fileObject['styleFiles'] = new FileCollection($fileObject['styleFiles']);
        $fileObject['scriptFiles'] = new FileCollection($fileObject['scriptFiles']);

        return $fileObject;
    }
}
