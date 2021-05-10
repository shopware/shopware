<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Cms\CmsExtensions as CmsManifest;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
class AppLoader extends AbstractAppLoader
{
    private string $appDir;

    private ConfigReader $configReader;

    private string $projectDir;

    public function __construct(string $appDir, string $projectDir, ConfigReader $configReader)
    {
        $this->appDir = $appDir;
        $this->configReader = $configReader;
        $this->projectDir = $projectDir;
    }

    public function getDecorated(): AbstractAppLoader
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return Manifest[]
     */
    public function load(): array
    {
        if (!file_exists($this->appDir)) {
            return [];
        }

        $finder = new Finder();
        $finder->in($this->appDir)
            ->depth('<= 1') // only use manifest files in app root folders
            ->name('manifest.xml');

        $manifests = [];
        foreach ($finder->files() as $xml) {
            try {
                $manifest = Manifest::createFromXmlFile($xml->getPathname());

                $manifests[$manifest->getMetadata()->getName()] = Manifest::createFromXmlFile($xml->getPathname());
            } catch (XmlParsingException $e) {
                //nth, if app is already registered it will be deleted
            }
        }

        return $manifests;
    }

    public function getIcon(Manifest $app): ?string
    {
        if (!$app->getMetadata()->getIcon()) {
            return null;
        }

        $iconPath = sprintf('%s/%s', $app->getPath(), $app->getMetadata()->getIcon() ?: '');
        $icon = @file_get_contents($iconPath);

        if (!$icon) {
            return null;
        }

        return $icon;
    }

    public function getConfiguration(AppEntity $app): ?array
    {
        $configPath = sprintf('%s/%s/Resources/config/config.xml', $this->projectDir, $app->getPath());

        if (!file_exists($configPath)) {
            return null;
        }

        return $this->configReader->read($configPath);
    }

    public function deleteApp(string $technicalName): void
    {
        $apps = $this->load();

        if (!isset($apps[$technicalName])) {
            return;
        }

        $manifest = $apps[$technicalName];

        (new Filesystem())->remove($manifest->getPath());
    }

    /**
     * @internal (flag:FEATURE_NEXT_14408)
     */
    public function getCmsExtensions(AppEntity $app): ?CmsManifest
    {
        if (!Feature::isActive('FEATURE_NEXT_14408')) {
            return null;
        }

        $configPath = sprintf('%s/%s/Resources/cms.xml', $this->projectDir, $app->getPath());

        if (!file_exists($configPath)) {
            return null;
        }

        return CmsManifest::createFromXmlFile($configPath);
    }
}
