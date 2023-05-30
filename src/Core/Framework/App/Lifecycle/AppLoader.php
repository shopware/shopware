<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Composer\InstalledVersions;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Cms\CmsExtensions as CmsManifest;
use Shopware\Core\Framework\App\FlowAction\FlowAction;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('core')]
class AppLoader extends AbstractAppLoader
{
    final public const COMPOSER_TYPE = 'shopware-app';

    public function __construct(
        private readonly string $appDir,
        private readonly string $projectDir,
        private readonly ConfigReader $configReader,
        private readonly CustomEntityXmlSchemaValidator $customEntityXmlValidator
    ) {
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
        return [...$this->loadFromAppDir(), ...$this->loadFromComposer()];
    }

    /**
     * @return array<mixed>|null
     */
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

        if ($manifest->isManagedByComposer()) {
            throw AppException::cannotDeleteManaged($technicalName);
        }

        (new Filesystem())->remove($manifest->getPath());
    }

    public function getCmsExtensions(AppEntity $app): ?CmsManifest
    {
        $configPath = sprintf('%s/%s/Resources/cms.xml', $this->projectDir, $app->getPath());

        if (!file_exists($configPath)) {
            return null;
        }

        return CmsManifest::createFromXmlFile($configPath);
    }

    public function getAssetPathForAppPath(string $appPath): string
    {
        return sprintf('%s/%s/Resources/public', $this->projectDir, $appPath);
    }

    public function getEntities(AppEntity $app): ?CustomEntityXmlSchema
    {
        $configPath = sprintf(
            '%s/%s/src/Resources/%s',
            $this->projectDir,
            $app->getPath(),
            CustomEntityXmlSchema::FILENAME
        );

        if (!file_exists($configPath)) {
            return null;
        }

        $entities = CustomEntityXmlSchema::createFromXmlFile($configPath);
        $this->customEntityXmlValidator->validate($entities);

        return $entities;
    }

    public function getFlowActions(AppEntity $app): ?FlowAction
    {
        $configPath = sprintf('%s/%s/Resources/flow-action.xml', $this->projectDir, $app->getPath());

        if (!file_exists($configPath)) {
            return null;
        }

        return FlowAction::createFromXmlFile($configPath);
    }

    /**
     * @return array<string, string>
     */
    public function getSnippets(AppEntity $app): array
    {
        $snippets = [];

        $path = sprintf('%s/%s/Resources/app/administration/snippet', $this->projectDir, $app->getPath());

        if (!file_exists($path)) {
            return $snippets;
        }

        $finder = new Finder();
        $finder->in($path)
            ->files()
            ->name('*.json');

        foreach ($finder->files() as $file) {
            $snippets[$file->getFilenameWithoutExtension()] = $file->getContents();
        }

        return $snippets;
    }

    public function loadFile(string $rootPath, string $filePath): ?string
    {
        $path = sprintf('%s/%s', $rootPath, $filePath);
        $content = @file_get_contents($path);

        if (!$content) {
            return null;
        }

        return $content;
    }

    /**
     * @return array<string, Manifest>
     */
    public function loadFromAppDir(): array
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

                $manifests[$manifest->getMetadata()->getName()] = $manifest;
            } catch (XmlParsingException) {
                //nth, if app is already registered it will be deleted
            }
        }

        return $manifests;
    }

    /**
     * @return array<string, Manifest>
     */
    private function loadFromComposer(): array
    {
        $manifests = [];

        foreach (InstalledVersions::getInstalledPackagesByType(self::COMPOSER_TYPE) as $packageName) {
            $path = InstalledVersions::getInstallPath($packageName);

            if ($path !== null) {
                try {
                    $manifest = Manifest::createFromXmlFile($path . '/manifest.xml');
                    $manifest->setManagedByComposer(true);

                    $manifests[$manifest->getMetadata()->getName()] = $manifest;
                } catch (XmlParsingException) {
                    //nth, if app is already registered it will be deleted
                }
            }
        }

        return $manifests;
    }
}
