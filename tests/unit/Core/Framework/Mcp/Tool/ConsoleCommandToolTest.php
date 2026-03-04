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
    public function testReturnsErrorWhenCommandNotInAllowlist(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $allowedCommands = ['cache:clear', 'debug:router'];

        $tool = new ConsoleCommandTool($kernel, $allowedCommands);
        $output = ($tool)('dangerous:command');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertArrayHasKey('error', $data);
        static::assertStringContainsString('dangerous:command', $data['error']);
        static::assertStringContainsString('cache:clear', $data['error']);
        static::assertStringContainsString('debug:router', $data['error']);
    }

    public function testReturnsErrorWithEmptyAllowlist(): void
    {
        $kernel = $this->createMock(KernelInterface::class);

        $tool = new ConsoleCommandTool($kernel, []);
        $output = ($tool)('any:command');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertSame('Command "any:command" is not in the allowlist. Allowed commands: ', $data['error']);
    }
}
