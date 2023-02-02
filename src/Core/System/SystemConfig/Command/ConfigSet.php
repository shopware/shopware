<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'system:config:set',
    description: 'Get a config value',
)]
#[Package('system-settings')]
class ConfigSet extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('key', InputArgument::REQUIRED)
            ->addArgument('value', InputArgument::REQUIRED)
            ->addOption('salesChannelId', 's', InputOption::VALUE_OPTIONAL)
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'If provided, the input value will be interpreted as JSON. Use this option to provide values as boolean, integer or float.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->systemConfigService->set(
            $input->getArgument('key'),
            $this->handleDecode($input),
            $input->getOption('salesChannelId')
        );

        return (int) Command::SUCCESS;
    }

    /**
     * @return array|bool|float|int|string|null $value
     */
    protected function handleDecode(InputInterface $input)
    {
        $value = $input->getArgument('value');
        if ($input->getOption('json')) {
            $decodedValue = json_decode((string) $value, true);

            if (json_last_error() === \JSON_ERROR_NONE) {
                return $decodedValue;
            }
        }

        return $value;
    }
}
