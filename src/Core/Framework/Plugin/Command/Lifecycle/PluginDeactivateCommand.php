<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\PluginNotActivatedException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginDeactivateCommand extends AbstractPluginLifecycleCommand
{
    private const LIFECYCLE_METHOD = 'deactivate';

    protected function configure(): void
    {
        $this->configureCommand(self::LIFECYCLE_METHOD);
    }

    /**
     * {@inheritdoc}
     *
     * @throws PluginNotInstalledException
     * @throws PluginNotActivatedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();
        $plugins = $this->prepareExecution(self::LIFECYCLE_METHOD, $io, $input, $context);

        $deactivated = 0;
        /** @var PluginEntity $plugin */
        foreach ($plugins as $plugin) {
            if ($plugin->getInstalledAt() === null) {
                $io->note(sprintf('Plugin "%s" must be installed. Skipping.', $plugin->getLabel()));

                continue;
            }

            if ($plugin->getActive() === false) {
                $io->note(sprintf('Plugin "%s" must be activated. Skipping.', $plugin->getLabel()));

                continue;
            }

            $this->pluginLifecycleService->deactivatePlugin($plugin, $context);
            ++$deactivated;

            $io->text(sprintf('Plugin "%s" has been deactivated successfully.', $plugin->getLabel()));
        }

        if ($deactivated !== 0) {
            $io->success(sprintf('Deactivated %d plugin(s).', $deactivated));
        }
    }
}
