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
    name: 'system:config:get',
    description: 'Get a config value',
)]
#[Package('system-settings')]
class ConfigGet extends Command
{
    private const FORMAT_DEFAULT = 'default';
    private const FORMAT_SCALAR = 'scalar';
    private const FORMAT_JSON = 'json';
    private const FORMAT_JSON_PRETTY = 'json-pretty';
    private const FORMAT_LEGACY = 'legacy';

    private const ALLOWED_FORMATS = [
        self::FORMAT_DEFAULT,
        self::FORMAT_SCALAR,
        self::FORMAT_JSON,
        self::FORMAT_JSON_PRETTY,
        self::FORMAT_LEGACY,
    ];

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
            ->addOption('salesChannelId', 's', InputOption::VALUE_OPTIONAL)
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Supported formats: ' . implode(', ', self::ALLOWED_FORMATS), self::FORMAT_LEGACY)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        if (!\in_array($format, self::ALLOWED_FORMATS, true)) {
            throw new \RuntimeException("{$format} is not a valid choice as output format");
        }

        $configKey = $input->getArgument('key');
        $value = $this->systemConfigService->get(
            $configKey,
            $input->getOption('salesChannelId')
        );

        if ($format === self::FORMAT_LEGACY) {
            $this->writeConfigLegacy($output, $value);

            return self::SUCCESS;
        }

        if ($format === self::FORMAT_SCALAR) {
            if (\is_array($value)) {
                throw new \RuntimeException('Value is an array, please specify the config key to point to a scalar, when using the scalar format.');
            }

            $this->writeConfigScalar($output, $this->getScalarValue($value));

            return self::SUCCESS;
        }

        if (!\is_array($value)) {
            $value = [$configKey => $value];
        }

        if (\in_array($format, [self::FORMAT_JSON, self::FORMAT_JSON_PRETTY], true)) {
            $flags = 0;
            if ($format === self::FORMAT_JSON_PRETTY) {
                $flags |= \JSON_PRETTY_PRINT;
            }

            $this->writeConfigJson($output, $value, $flags);
        } elseif ($format === 'default') {
            $this->writeConfigDefault($output, $value);
        }

        return self::SUCCESS;
    }

    private function writeConfigScalar(OutputInterface $output, string $config): void
    {
        $output->writeln($config);
    }

    /**
     * @param array|bool|float|int|string|null $config
     */
    private function writeConfigLegacy(OutputInterface $output, $config): void
    {
        if (\is_array($config)) {
            ksort($config);

            $output->writeln($config);
        } else {
            $output->writeln((string) $config);
        }
    }

    private function writeConfigJson(OutputInterface $output, array $config, int $flags): void
    {
        $output->writeln((string) \json_encode($config, $flags));
    }

    private function writeConfigDefault(OutputInterface $output, array $config, int $level = 1): void
    {
        ksort($config);

        foreach ($config as $key => $entry) {
            if (\is_array($entry)) {
                $output->writeln($key);
                $this->writeConfigDefault($output, $entry, $level + 1);
            } else {
                $output->writeln(\str_repeat(' ', $level * 2) . "{$key} => {$this->getScalarValue($entry)}");
            }
        }
    }

    /**
     * @param bool|float|int|string|null $value
     */
    private function getScalarValue($value): string
    {
        if (\is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
