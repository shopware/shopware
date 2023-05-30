<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Changelog\ChangelogParser;
use Shopware\Core\Framework\Plugin\Changelog\ChangelogService;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Plugin\Util\VersionSanitizer;
use SwagTest\SwagTest;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait PluginTestsHelper
{
    protected function createPluginService(
        string $pluginDir,
        string $projectDir,
        EntityRepository $pluginRepo,
        EntityRepository $languageRepo,
        PluginFinder $pluginFinder
    ): PluginService {
        return new PluginService(
            $pluginDir,
            $projectDir,
            $pluginRepo,
            $languageRepo,
            new ChangelogService(new ChangelogParser()),
            $pluginFinder,
            new VersionSanitizer()
        );
    }

    protected function createPlugin(
        EntityRepository $pluginRepo,
        Context $context,
        string $version = SwagTest::PLUGIN_VERSION,
        ?string $installedAt = null
    ): void {
        $pluginRepo->create(
            [
                [
                    'baseClass' => SwagTest::class,
                    'name' => 'SwagTest',
                    'version' => $version,
                    'label' => SwagTest::PLUGIN_LABEL,
                    'installedAt' => $installedAt,
                    'active' => false,
                    'autoload' => [],
                ],
            ],
            $context
        );
    }

    abstract protected static function getContainer(): ContainerInterface;

    private function addTestPluginToKernel(string $testPluginBaseDir, string $pluginName, bool $active = false): void
    {
        require_once $testPluginBaseDir . '/src/' . $pluginName . '.php';

        /** @var KernelPluginCollection $pluginCollection */
        $pluginCollection = $this->getContainer()->get(KernelPluginCollection::class);
        /** @var class-string<Plugin> $class */
        $class = '\\' . $pluginName . '\\' . $pluginName;
        $plugin = new $class($active, $testPluginBaseDir);
        $pluginCollection->add($plugin);

        $this->getContainer()->get(KernelPluginLoader::class)->getPluginInstances()->add($plugin);
    }
}
