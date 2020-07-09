<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Command;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigSet extends Command
{
    protected static $defaultName = 'system:config:set';

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        parent::__construct();
        $this->systemConfigService = $systemConfigService;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('key', InputArgument::REQUIRED)
            ->addArgument('value', InputArgument::REQUIRED)
            ->addOption('salesChannelId', 's', InputOption::VALUE_OPTIONAL)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->systemConfigService->set(
            $input->getArgument('key'),
            $input->getArgument('value'),
            $input->getOption('salesChannelId')
        );

        return 0;
    }
}
