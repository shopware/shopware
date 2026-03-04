<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-console-command', description: 'Execute allowlisted Shopware console commands. Only safe, read-only or administrative commands are permitted. Use --format=json where available for structured output.')]
#[Package('framework')]
class ConsoleCommandTool
{
    private ?Application $application = null;

    /**
     * @param list<string> $allowedCommands
     */
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly array $allowedCommands,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(string $command, string $arguments = '{}'): string
    {
        if (!\in_array($command, $this->allowedCommands, true)) {
            return json_encode([
                'success' => false,
                'error' => \sprintf('Command "%s" is not in the allowlist. Allowed commands: %s', $command, implode(', ', $this->allowedCommands)),
            ], \JSON_THROW_ON_ERROR);
        }

        $args = json_decode($arguments, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($args)) {
            $args = [];
        }

        $args['command'] = $command;
        $args['--no-interaction'] = true;

        $input = new ArrayInput($args);
        $output = new BufferedOutput();

        $startTime = microtime(true);

        try {
            $application = $this->getApplication();
            $exitCode = $application->run($input, $output);

            $duration = round((microtime(true) - $startTime) * 1000);
            $rawOutput = $this->stripAnsiCodes($output->fetch());

            $this->logger?->info('MCP console command executed', [
                'command' => $command,
                'arguments' => $args,
                'exitCode' => $exitCode,
                'durationMs' => $duration,
            ]);

            return json_encode([
                'success' => $exitCode === 0,
                'exitCode' => $exitCode,
                'output' => $rawOutput,
                'durationMs' => $duration,
            ], \JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000);

            $this->logger?->error('MCP console command failed', [
                'command' => $command,
                'arguments' => $args,
                'error' => $e->getMessage(),
                'durationMs' => $duration,
            ]);

            return json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'output' => $this->stripAnsiCodes($output->fetch()),
                'durationMs' => $duration,
            ], \JSON_THROW_ON_ERROR);
        }
    }

    private function getApplication(): Application
    {
        if ($this->application === null) {
            $this->application = new Application($this->kernel);
            $this->application->setAutoExit(false);
        }

        return $this->application;
    }

    private function stripAnsiCodes(string $text): string
    {
        return (string) preg_replace('/\x1B\[[0-9;]*[A-Za-z]/', '', $text);
    }
}
