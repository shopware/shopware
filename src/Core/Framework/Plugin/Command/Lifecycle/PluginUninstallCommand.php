<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginUninstallCommand extends AbstractPluginLifecycleCommand
{
    private const LIFECYCLE_METHOD = 'uninstall';

    protected function configure(): void
    {
        $this->configureCommand(self::LIFECYCLE_METHOD);
        $this->addOption('keep-user-data', null, InputOption::VALUE_NONE, 'Keep user data of the plugin');
    }

    /**
     * {@inheritdoc}
     *
     * @throws PluginNotInstalledException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();
        $plugins = $this->prepareExecution(self::LIFECYCLE_METHOD, $io, $input, $context);

        $keepUserData = (bool) $input->getOption('keep-user-data');

        $uninstalled = 0;
        /** @var PluginEntity $plugin */
        foreach ($plugins as $plugin) {
            if ($plugin->getInstalledAt() === null) {
                $io->note(sprintf('Plugin "%s" is not installed. Skipping.', $plugin->getLabel()));

                continue;
            }

            $this->pluginLifecycleService->uninstallPlugin($plugin, $context, $keepUserData);
            ++$uninstalled;

            $io->text(sprintf('Plugin "%s" has been uninstalled successfully.', $plugin->getLabel()));
        }

        if ($uninstalled !== 0) {
            $io->success(sprintf('Uninstalled %d plugins.', $uninstalled));
        }
    }
}
