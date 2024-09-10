<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Cms\CmsExtensions as CmsManifest;
use Shopware\Core\Framework\App\Flow\Action\Action;
use Shopware\Core\Framework\App\Flow\Event\Event;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('core')]
abstract class AbstractAppLoader
{
    public function __construct(private readonly ConfigReader $configReader)
    {
    }

    abstract public function getDecorated(): AbstractAppLoader;

    /**
     * @return array<string, Manifest>
     */
    abstract public function load(): array;

    /**
     * @return array<array<string, mixed>>|null
     */
    public function getConfiguration(AppEntity $app): ?array
    {
        $configPath = $this->locatePath($app->getPath(), 'Resources/config/config.xml');

        if ($configPath === null) {
            return null;
        }

        return $this->configReader->read($configPath);
    }

    abstract public function deleteApp(string $technicalName): void;

    public function getCmsExtensions(AppEntity $app): ?CmsManifest
    {
        $configPath = $this->locatePath($app->getPath(), 'Resources/cms.xml');

        if ($configPath === null) {
            return null;
        }

        return CmsManifest::createFromXmlFile($configPath);
    }

    public function getFlowActions(AppEntity $app): ?Action
    {
        $configPath = $this->locatePath($app->getPath(), 'Resources/flow.xml');

        if ($configPath === null) {
            return null;
        }

        return Action::createFromXmlFile($configPath);
    }

    public function getFlowEvents(AppEntity $app): ?Event
    {
        $configPath = $this->locatePath($app->getPath(), 'Resources/flow.xml');

        if ($configPath === null) {
            return null;
        }

        return Event::createFromXmlFile($configPath);
    }

    /**
     * @return array<string, string>
     */
    public function getSnippets(AppEntity $app): array
    {
        $path = $this->locatePath($app->getPath(), 'Resources/app/administration/snippet');

        if ($path === null) {
            return [];
        }

        $finder = new Finder();
        $finder->in($path)
            ->files()
            ->name('*.json');

        $snippets = [];

        foreach ($finder->files() as $file) {
            $snippets[$file->getFilenameWithoutExtension()] = $file->getContents();
        }

        return $snippets;
    }

    abstract public function loadFile(string $appPath, string $filePath): ?string;

    abstract public function locatePath(string $appPath, string $filePath): ?string;
}
