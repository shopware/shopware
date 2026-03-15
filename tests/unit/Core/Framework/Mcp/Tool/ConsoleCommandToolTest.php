<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\ConsoleCommandTool;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConsoleCommandTool::class)]
class ConsoleCommandToolTest extends TestCase
{
    /**
     * Mirrors the defaultValue array in Configuration.php::createMcpSection().
     * Update here whenever the allowlist in Configuration.php changes.
     *
     * @var list<string>
     */
    private const PRODUCTION_ALLOWLIST = [
        'about',
        'cache:clear',
        'cache:warmup',
        'plugin:list',
        'plugin:refresh',
        'plugin:install',
        'plugin:activate',
        'plugin:deactivate',
        'plugin:uninstall',
        'scheduled-task:list',
        'scheduled-task:run',
        'theme:compile',
        'debug:router',
        'debug:mcp',
        'messenger:stats',
        'assets:install',
        'debug:container',
        'debug:event-dispatcher',
        'debug:autowiring',
        'debug:dotenv',
        'messenger:failed:show',
    ];

    private KernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(KernelInterface::class);
    }

    // -----------------------------------------------------------------------
    // Allowlist rejection — no kernel interaction needed for these
    // -----------------------------------------------------------------------

    public function testCommandNotInAllowlistReturnsError(): void
    {
        $tool = new ConsoleCommandTool($this->kernel, ['cache:clear']);
        $result = json_decode(($tool)('arbitrary:command', '{}'), true);

        static::assertFalse($result['success']);
        static::assertStringContainsString('not in the allowlist', $result['error']);
    }

    public function testSchedulerStatusIsNotInAllowlist(): void
    {
        // scheduler:status does not exist in Shopware — the platform uses
        // scheduled-task:* commands instead. Verifies it was not accidentally added.
        $tool = new ConsoleCommandTool($this->kernel, self::PRODUCTION_ALLOWLIST);
        $result = json_decode(($tool)('scheduler:status', '{}'), true);

        static::assertFalse($result['success']);
        static::assertStringContainsString('not in the allowlist', $result['error']);
    }

    public function testAllowlistErrorMessageListsAllowedCommands(): void
    {
        $tool = new ConsoleCommandTool($this->kernel, ['cache:clear', 'plugin:list']);
        $result = json_decode(($tool)('bogus:command', '{}'), true);

        static::assertStringContainsString('cache:clear', $result['error']);
        static::assertStringContainsString('plugin:list', $result['error']);
    }

    // -----------------------------------------------------------------------
    // Allowlist acceptance — each new command must pass the guard.
    // The mock kernel will cause a failure at execution time, but the error
    // must not be "not in the allowlist". That distinction is what we test.
    // -----------------------------------------------------------------------

    public function testDebugContainerIsInAllowlist(): void
    {
        $tool = new ConsoleCommandTool($this->kernel, self::PRODUCTION_ALLOWLIST);
        $result = json_decode(($tool)('debug:container', '{}'), true);

        static::assertStringNotContainsString('not in the allowlist', $result['error'] ?? '');
    }

    public function testDebugEventDispatcherIsInAllowlist(): void
    {
        $tool = new ConsoleCommandTool($this->kernel, self::PRODUCTION_ALLOWLIST);
        $result = json_decode(($tool)('debug:event-dispatcher', '{}'), true);

        static::assertStringNotContainsString('not in the allowlist', $result['error'] ?? '');
    }

    public function testDebugAutowiringIsInAllowlist(): void
    {
        $tool = new ConsoleCommandTool($this->kernel, self::PRODUCTION_ALLOWLIST);
        $result = json_decode(($tool)('debug:autowiring', '{}'), true);

        static::assertStringNotContainsString('not in the allowlist', $result['error'] ?? '');
    }

    public function testDebugDotenvIsInAllowlist(): void
    {
        $tool = new ConsoleCommandTool($this->kernel, self::PRODUCTION_ALLOWLIST);
        $result = json_decode(($tool)('debug:dotenv', '{}'), true);

        static::assertStringNotContainsString('not in the allowlist', $result['error'] ?? '');
    }

    public function testScheduledTaskRunIsInAllowlist(): void
    {
        $tool = new ConsoleCommandTool($this->kernel, self::PRODUCTION_ALLOWLIST);
        $result = json_decode(($tool)('scheduled-task:run', '{}'), true);

        static::assertStringNotContainsString('not in the allowlist', $result['error'] ?? '');
    }

    public function testMessengerFailedShowIsInAllowlist(): void
    {
        $tool = new ConsoleCommandTool($this->kernel, self::PRODUCTION_ALLOWLIST);
        $result = json_decode(($tool)('messenger:failed:show', '{}'), true);

        static::assertStringNotContainsString('not in the allowlist', $result['error'] ?? '');
    }

    // -----------------------------------------------------------------------
    // Input validation
    // -----------------------------------------------------------------------

    public function testReturnTypeIsAlwaysString(): void
    {
        $tool = new ConsoleCommandTool($this->kernel, []);
        $result = ($tool)('any:command', '{}');

        static::assertIsString($result);
        $decoded = json_decode($result, true);
        static::assertIsArray($decoded);
        static::assertArrayHasKey('success', $decoded);
    }

    public function testInvalidJsonArgumentsReturnsError(): void
    {
        $tool = new ConsoleCommandTool($this->kernel, ['cache:clear']);
        $result = json_decode(($tool)('cache:clear', '{invalid json}'), true);

        static::assertFalse($result['success']);
        static::assertStringContainsString('Invalid JSON', $result['error']);
    }
}
