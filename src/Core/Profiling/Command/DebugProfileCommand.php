<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Export\ExportLimits;
use Shopware\Core\Profiling\Export\ProfileExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[AsCommand(
    name: 'debug:profile',
    description: 'Lists stored profiler profiles, or exports one profile as JSON',
)]
class DebugProfileCommand extends Command
{
    private const TYPES = ['request', 'command'];

    public function __construct(
        private readonly ProfileExporter $exporter,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('token', InputArgument::OPTIONAL, 'Profile token, or "latest" for the newest profile that matches the filters. Without a token, the command lists profiles.')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'Only profiles whose URL contains this value')
            ->addOption('method', null, InputOption::VALUE_REQUIRED, 'Only profiles with this HTTP method')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Only profiles with this status code')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Profile type: "request", or "command" for "bin/console --profile" runs', 'request')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Number of profiles to list', '20')
            ->addOption('panel', 'p', InputOption::VALUE_REQUIRED, 'Comma-separated panels to export, by panel or collector name, for example "db,time"')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Export the raw collector data instead of the summary')
            ->addOption('max-depth', null, InputOption::VALUE_REQUIRED, 'Maximum nesting depth of raw data, 0 for no limit (default: 3, with --full 12)')
            ->addOption('max-items', null, InputOption::VALUE_REQUIRED, 'Maximum items per array of raw data, 0 for no limit (default: 5, with --full 1000)')
            ->addOption('max-string', null, InputOption::VALUE_REQUIRED, 'Maximum string length, 0 for no limit (default: 120, with --full 10000; curated summaries keep at least 500)')
            ->addOption('keep-secrets', null, InputOption::VALUE_NONE, 'Do not redact credentials, tokens, cookies and session values')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write the JSON to this file instead of stdout');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorOutput = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        $type = (string) $input->getOption('type');
        if (!\in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException(\sprintf('The option "--type" must be one of "%s".', implode('", "', self::TYPES)));
        }

        $url = $this->stringOption($input, 'url');
        $method = $this->stringOption($input, 'method');
        $status = $this->stringOption($input, 'status');

        $token = $input->getArgument('token');
        if (!\is_string($token) || $token === '') {
            $limit = $this->intOption($input, 'limit') ?? 20;

            return $this->write(['profiles' => $this->exporter->list($url, $method, $status, $type, $limit, !$input->getOption('keep-secrets'))], $input, $output);
        }

        $profile = $this->exporter->load($token, $url, $method, $status, $type);
        if ($profile === null) {
            $errorOutput->error(\sprintf('No profile found for "%s".', $token));

            return self::FAILURE;
        }

        $panels = array_values(array_filter(array_map(trim(...), explode(',', $this->stringOption($input, 'panel') ?? ''))));
        $available = $this->exporter->panels($profile);
        $unknown = array_diff($panels, array_keys($available), $available);
        if ($unknown !== []) {
            $errorOutput->error(\sprintf('Unknown panel "%s". Available panels: %s', implode('", "', $unknown), implode(', ', $available)));

            return self::FAILURE;
        }

        $full = (bool) $input->getOption('full');
        $defaults = $full ? ExportLimits::full() : ExportLimits::summary();
        $limits = new ExportLimits(
            depth: $this->intOption($input, 'max-depth') ?? $defaults->depth,
            items: $this->intOption($input, 'max-items') ?? $defaults->items,
            string: $this->intOption($input, 'max-string') ?? $defaults->string,
        );

        $export = $this->exporter->export($profile, $panels, $full, $limits, !$input->getOption('keep-secrets'));

        return $this->write($export, $input, $output);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function write(array $payload, InputInterface $input, OutputInterface $output): int
    {
        $json = json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR);

        $file = $this->stringOption($input, 'output');
        if ($file === null) {
            $output->writeln($json, OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        $this->filesystem->dumpFile($file, $json . \PHP_EOL);
        $output->writeln(\sprintf('%s (%d bytes)', $file, \strlen($json) + \strlen(\PHP_EOL)), OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return \is_string($value) && $value !== '' ? $value : null;
    }

    private function intOption(InputInterface $input, string $name): ?int
    {
        $value = $this->stringOption($input, $name);
        if ($value === null) {
            return null;
        }

        if (!ctype_digit($value)) {
            throw new \InvalidArgumentException(\sprintf('The option "--%s" must be a number of 0 or more.', $name));
        }

        return (int) $value;
    }
}
