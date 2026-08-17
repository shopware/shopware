<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Composer\Package\Link;
use Composer\Package\Package as ComposerPackage;
use Composer\Package\PackageInterface;
use Composer\Util\PackageSorter;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Composer\Factory;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;

#[Package('framework')]
#[AsCommand(
    name: 'plugin:install',
    description: 'Installs a plugin',
)]
class PluginInstallCommand extends AbstractPluginLifecycleCommand
{
    private const LIFECYCLE_METHOD = 'install';

    /**
     * @internal
     *
     * @param EntityRepository<PluginCollection> $pluginRepo
     */
    public function __construct(
        PluginLifecycleService $pluginLifecycleService,
        EntityRepository $pluginRepo,
        CacheClearer $cacheClearer,
        private readonly string $projectDir
    ) {
        parent::__construct($pluginLifecycleService, $pluginRepo, $cacheClearer);
    }

    protected function configure(): void
    {
        $this->configureCommand(self::LIFECYCLE_METHOD);
        $this->addOption('activate', 'a', InputOption::VALUE_NONE, 'Activate plugins after installation.')
            ->addOption('reinstall', null, InputOption::VALUE_NONE, 'Reinstall the plugins');
    }

    /**
     * {@inheritdoc}
     *
     * @throws PluginNotInstalledException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createCLIContext();
        $plugins = $this->prepareExecution(self::LIFECYCLE_METHOD, $io, $input, $context);

        if ($plugins === null) {
            return self::SUCCESS;
        }

        $activatePlugins = $input->getOption('activate');
        $plugins = $this->sortPluginsByRequirements($plugins);

        $installedPluginCount = 0;
        foreach ($plugins as $plugin) {
            if ($input->getOption('reinstall') && $plugin->getInstalledAt()) {
                $this->pluginLifecycleService->uninstallPlugin($plugin, $context);
            }

            if ($activatePlugins && $plugin->getInstalledAt() && $plugin->getActive() === false) {
                $io->note(\sprintf('Plugin "%s" is already installed. Activating.', $plugin->getName()));
                $this->pluginLifecycleService->activatePlugin($plugin, $context);

                continue;
            }

            if ($plugin->getInstalledAt()) {
                $io->note(\sprintf('Plugin "%s" is already installed. Skipping.', $plugin->getName()));

                continue;
            }

            $activationSuffix = '';
            $message = 'Plugin "%s" has been installed%s successfully.';

            $this->pluginLifecycleService->installPlugin($plugin, $context);
            ++$installedPluginCount;

            if ($activatePlugins) {
                if ($input->getOption('refresh')) {
                    $io->note('Can not refresh and activate in same request.');
                } else {
                    // do not validate requirements here, as the plugin was already installed and would have thrown an exception if the requirements were not met.
                    $this->pluginLifecycleService->activatePlugin($plugin, $context, validateRequirements: false);
                    $activationSuffix = ' and activated';
                }
            }

            $io->text(\sprintf($message, $plugin->getName(), $activationSuffix));
        }

        if ($installedPluginCount !== 0) {
            $io->success(\sprintf('Installed %d plugin(s).', $installedPluginCount));
        }

        if ($activatePlugins) {
            $this->handleClearCache($input, $io, 'activating');
        }

        return self::SUCCESS;
    }

    private function sortPluginsByRequirements(PluginCollection $plugins): PluginCollection
    {
        if ($plugins->count() <= 1) {
            return $plugins;
        }

        // This only sorts selected plugins that are known before installation starts. The command
        // does not reload Composer's autoloader after a plugin installs new PHP packages, so later
        // plugins can use those packages only in a new CLI process.
        $pluginPackages = [];
        $pluginsByPackageName = [];
        $packageAliases = [];

        foreach ($plugins as $plugin) {
            $package = $this->getPluginPackage($plugin);
            $packageName = strtolower($package->getName());

            $pluginPackages[] = [$package, $packageName];
            $pluginsByPackageName[$packageName] = $plugin;

            // PackageSorter matches requirement targets literally. A Store plugin can have a
            // different project-facing composer name (for example store.shopware.com/foo) than
            // the name in the composer.json shipped inside the plugin archive.
            $packageAliases[$packageName] = $packageName;

            if ($plugin->getComposerName() !== null) {
                $packageAliases[strtolower($plugin->getComposerName())] = $packageName;
            }
        }

        $packages = [];
        foreach ($pluginPackages as [$package, $packageName]) {
            $requires = [];

            foreach ($package->getRequires() as $requirement) {
                $target = $packageAliases[strtolower($requirement->getTarget())] ?? $requirement->getTarget();

                // Normalize aliases to the package node used by the sorter. Without a matching
                // node, PackageSorter silently drops the dependency edge and may sort alphabetically.
                $requires[$target] = $target === $requirement->getTarget()
                    ? $requirement
                    : new Link(
                        $packageName,
                        $target,
                        $requirement->getConstraint(),
                        Link::TYPE_REQUIRE,
                        $requirement->getPrettyConstraint()
                    );
            }

            $sortPackage = new ComposerPackage($packageName, $package->getVersion(), $package->getPrettyVersion());
            $sortPackage->setRequires($requires);
            $packages[] = $sortPackage;
        }

        $sortedPlugins = [];
        foreach (PackageSorter::sortPackages($packages) as $package) {
            $sortedPlugins[] = $pluginsByPackageName[strtolower($package->getName())];
        }

        return new PluginCollection($sortedPlugins);
    }

    private function getPluginPackage(PluginEntity $plugin): PackageInterface
    {
        $pluginPath = $plugin->getPath();
        \assert($pluginPath !== null);

        $pluginDirectory = Path::join($this->projectDir, $pluginPath);
        $composerJsonPath = Path::join($pluginDirectory, 'composer.json');

        if (!is_file($composerJsonPath)) {
            throw PluginException::composerJsonMissing($plugin->getName(), $composerJsonPath);
        }

        return Factory::createComposer($pluginDirectory)->getPackage();
    }
}
