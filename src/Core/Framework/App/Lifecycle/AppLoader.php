<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Composer\InstalledVersions;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
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
        ConfigReader $configReader,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($configReader);
    }

    public function getDecorated(): AbstractAppLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(): array
    {
        return [...$this->loadFromAppDir(), ...$this->loadFromComposer()];
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

    public function loadFile(string $appPath, string $filePath): ?string
    {
        $path = Path::join($appPath, $filePath);

        if ($path[0] !== \DIRECTORY_SEPARATOR) {
            $path = Path::join($this->projectDir, $path);
        }

        $content = @file_get_contents($path);

        if (!$content) {
            return null;
        }

        return $content;
    }

    public function locatePath(string $appPath, string $filePath): ?string
    {
        $path = Path::join($appPath, $filePath);

        if ($path[0] !== \DIRECTORY_SEPARATOR) {
            $path = Path::join($this->projectDir, $path);
        }

        if (!file_exists($path)) {
            return null;
        }

        return $path;
    }

    /**
     * @return array<string, Manifest>
     */
    private function loadFromAppDir(): array
    {
        if (!file_exists($this->appDir)) {
            return [];
        }

        $finder = new Finder();
        $finder->in($this->appDir)
            ->depth('<= 1') // only use manifest files in-app root folders
            ->name('manifest.xml');

        $manifests = [];
        foreach ($finder->files() as $xml) {
            try {
                $manifest = Manifest::createFromXmlFile($xml->getPathname());

                $manifests[$manifest->getMetadata()->getName()] = $manifest;
            } catch (AppXmlParsingException|XmlParsingException $exception) {
                $this->logger->error('Manifest XML parsing error. Reason: ' . $exception->getMessage(), ['trace' => $exception->getTrace()]);
            }
        }

        // Overriding with local manifests
        $finder = new Finder();

        $finder->in($this->appDir)
            ->depth('<= 1') // only use manifest files in-app root folders
            ->name('manifest.local.xml');

        foreach ($finder->files() as $xml) {
            try {
                $manifest = Manifest::createFromXmlFile($xml->getPathname());

                $manifests[$manifest->getMetadata()->getName()] = $manifest;
            } catch (AppXmlParsingException|XmlParsingException $exception) {
                $this->logger->error('Local manifest XML parsing error. Reason: ' . $exception->getMessage(), ['trace' => $exception->getTrace()]);
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
                } catch (AppXmlParsingException|XmlParsingException $exception) {
                    $this->logger->error('Manifest XML parsing error. Reason: ' . $exception->getMessage(), ['trace' => $exception->getTrace()]);
                }
            }
        }

        return $manifests;
    }
}
