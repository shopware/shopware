<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginUpdateCommand extends AbstractPluginLifecycleCommand
{
    private const LIFECYCLE_METHOD = 'update';

    protected function configure(): void
    {
        $this->configureCommand(self::LIFECYCLE_METHOD);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();
        $plugins = $this->prepareExecution(self::LIFECYCLE_METHOD, $io, $input, $context);

        $updated = 0;
        /** @var PluginEntity $plugin */
        foreach ($plugins as $plugin) {
            $this->pluginLifecycleService->updatePlugin($plugin, $context);
            ++$updated;

            $io->text(sprintf('Plugin "%s" has been updated successfully.', $plugin->getLabel()));
        }

        if ($updated !== 0) {
            $io->success(sprintf('Updated %d plugin(s).', $updated));
        }
    }
}
