<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'plugin:install',
    description: 'Installs a plugin',
)]
#[Package('core')]
class PluginInstallCommand extends AbstractPluginLifecycleCommand
{
    private const LIFECYCLE_METHOD = 'install';

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
        $io = new ShopwareStyle($input, $output);
        $context = Context::createDefaultContext();
        $plugins = $this->prepareExecution(self::LIFECYCLE_METHOD, $io, $input, $context);

        if ($plugins === null) {
            return self::SUCCESS;
        }

        $activatePlugins = $input->getOption('activate');

        $installedPluginCount = 0;
        foreach ($plugins as $plugin) {
            if ($input->getOption('reinstall') && $plugin->getInstalledAt()) {
                $this->pluginLifecycleService->uninstallPlugin($plugin, $context);
            }

            if ($activatePlugins && $plugin->getInstalledAt() && $plugin->getActive() === false) {
                $io->note(sprintf('Plugin "%s" is already installed. Activating.', $plugin->getName()));
                $this->pluginLifecycleService->activatePlugin($plugin, $context);

                continue;
            }

            if ($plugin->getInstalledAt()) {
                $io->note(sprintf('Plugin "%s" is already installed. Skipping.', $plugin->getName()));

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
                    $this->pluginLifecycleService->activatePlugin($plugin, $context);
                    $activationSuffix = ' and activated';
                }
            }

            $io->text(sprintf($message, $plugin->getName(), $activationSuffix));
        }

        if ($installedPluginCount !== 0) {
            $io->success(sprintf('Installed %d plugin(s).', $installedPluginCount));
        }

        if ($activatePlugins) {
            $this->handleClearCacheOption($input, $io, 'activating');
        }

        return self::SUCCESS;
    }
}
