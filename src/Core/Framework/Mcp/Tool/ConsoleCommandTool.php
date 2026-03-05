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
    use McpToolResponse;

    private ?Application $application = null;

    /**
     * @internal
     *
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
            return $this->error(\sprintf('Command "%s" is not in the allowlist. Allowed commands: %s', $command, implode(', ', $this->allowedCommands)));
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

            return $this->success([
                'exitCode' => $exitCode,
                'output' => $rawOutput,
                'durationMs' => $duration,
            ]);
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000);

            $this->logger?->error('MCP console command failed', [
                'command' => $command,
                'arguments' => $args,
                'error' => $e->getMessage(),
                'durationMs' => $duration,
            ]);

            return $this->error($e->getMessage());
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
