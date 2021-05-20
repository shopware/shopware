<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Composer\IO\ConsoleIO;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

class PluginRefreshCommand extends Command
{
    protected static $defaultName = 'plugin:refresh';

    /**
     * @var PluginService
     */
    private $pluginService;

    public function __construct(PluginService $pluginService)
    {
        parent::__construct();

        $this->pluginService = $pluginService;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Refreshes the plugins list in the storage from the file system')
            ->addOption('skipPluginList', 's', InputOption::VALUE_NONE, "Don't display plugin list after refresh");
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $io->title('Shopware Plugin Service');
        $context = Context::createDefaultContext();
        $errors = $this->pluginService->refreshPlugins($context, new ConsoleIO($input, $output, new HelperSet()));
        $io->success('Plugin list refreshed');

        if (\count($errors) !== 0) {
            $io->writeln('Errors occurred while refreshing plugin list');
            foreach ($errors as $key => $error) {
                if (\is_int($key)) {
                    $io->error($error->getMessage());
                } else {
                    $io->error($key . ': ' . $error->getMessage());
                }
            }
        }

        $skipPluginList = $input->getOption('skipPluginList');
        if ($skipPluginList) {
            return self::SUCCESS;
        }

        $listInput = new StringInput('plugin:list');

        /** @var Application $application */
        $application = $this->getApplication();
        $application->doRun($listInput, $output);

        return self::SUCCESS;
    }
}
