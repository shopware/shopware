<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Command;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tails the most recent Shopware log file and returns lines filtered by level.
 *
 * Designed for use via the shopware-console-command MCP tool so the AI bridge
 * can retrieve live structured error entries without direct filesystem access.
 *
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[AsCommand(name: 'app:log:show', description: 'Show the last N log entries at or above the specified level.')]
#[Package('framework')]
class AppLogShowCommand extends Command
{
    private const LEVEL_PRIORITY = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7,
    ];

    /**
     * @internal
     */
    public function __construct(private readonly string $logsDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('level', null, InputOption::VALUE_REQUIRED, 'Minimum log level (debug|info|notice|warning|error|critical|alert|emergency)', 'error')
            ->addOption('lines', null, InputOption::VALUE_REQUIRED, 'Maximum number of matching lines to return', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $level = strtolower((string) $input->getOption('level'));
        $lines = max(1, (int) $input->getOption('lines'));
        $minPriority = self::LEVEL_PRIORITY[$level] ?? self::LEVEL_PRIORITY['error'];

        $logFile = $this->findLatestLogFile();
        if ($logFile === null) {
            $output->writeln('No log file found in ' . $this->logsDir);

            return Command::SUCCESS;
        }

        $matched = $this->collectMatchingLines($logFile, $minPriority, $lines);

        if ($matched === []) {
            $output->writeln(sprintf('No entries at or above "%s" level found in %s.', $level, basename($logFile)));

            return Command::SUCCESS;
        }

        foreach ($matched as $line) {
            $output->writeln($line);
        }

        return Command::SUCCESS;
    }

    private function findLatestLogFile(): ?string
    {
        if (!is_dir($this->logsDir)) {
            return null;
        }

        $files = glob($this->logsDir . '/*.log');
        if ($files === false || $files === []) {
            return null;
        }

        usort($files, static fn (string $a, string $b): int => (int) filemtime($b) - (int) filemtime($a));

        return $files[0];
    }

    /**
     * Reads the log file from the end and collects the last N lines whose level
     * is at or above $minPriority.
     *
     * Monolog line format: [ISO8601] channel.LEVEL: message {context} []
     *
     * @return list<string>
     */
    private function collectMatchingLines(string $file, int $minPriority, int $maxLines): array
    {
        $handle = @fopen($file, 'r');
        if ($handle === false) {
            return [];
        }

        $all = [];
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line !== false && trim($line) !== '') {
                $all[] = rtrim($line);
            }
        }
        fclose($handle);

        $matched = [];
        foreach (array_reverse($all) as $line) {
            if (preg_match('/\]\s+\w+\.([A-Z]+):/i', $line, $m)) {
                $linePriority = self::LEVEL_PRIORITY[strtolower($m[1])] ?? -1;
                if ($linePriority >= $minPriority) {
                    $matched[] = $line;
                    if (count($matched) >= $maxLines) {
                        break;
                    }
                }
            }
        }

        return array_reverse($matched);
    }
}
