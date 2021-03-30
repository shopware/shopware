<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Command;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigGet extends Command
{
    protected static $defaultName = 'system:config:get';

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
            ->addOption('salesChannelId', 's', InputOption::VALUE_OPTIONAL)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configKey = $input->getArgument('key');
        $value = $this->systemConfigService->get(
            $configKey,
            $input->getOption('salesChannelId')
        );

        if (!\is_array($value)) {
            $value = [$configKey => $value];
        }

        $this->writeConfig($output, $value);

        return 0;
    }

    private function writeConfig(OutputInterface $output, array $config, int $level = 0): void
    {
        foreach ($config as $key => $entry) {
            if (\is_array($entry)) {
                $output->writeln($key);
                $this->writeConfig($output, $entry, $level + 1);
            } else {
                $output->writeln(str_repeat(' ', $level * 2) . "{$key} => {$entry}");
            }
        }
    }
}
