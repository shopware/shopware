<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\ConsoleCommandTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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

    public function testThrowsWhenArgumentsIsInvalidJson(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $tool = new ConsoleCommandTool($kernel, ['debug:router']);

        $this->expectException(\JsonException::class);

        ($tool)('debug:router', 'not json');
    }

    public function testNonArrayJsonDecodesGracefully(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $application = $this->createMock(Application::class);
        $application->method('run')->willReturn(0);

        $tool = new ConsoleCommandTool($kernel, ['debug:router']);
        $this->injectApplication($tool, $application);

        $output = ($tool)('debug:router', '"just a string"');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame(0, $data['data']['exitCode']);
    }

    public function testSuccessPathReturnsExitCodeAndStripsAnsiCodes(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $application = $this->createMock(Application::class);
        $application->method('run')
            ->willReturnCallback(function (InputInterface $input, OutputInterface $output): int {
                $output->writeln("\033[32mSuccess\033[0m output");

                return 0;
            });

        $tool = new ConsoleCommandTool($kernel, ['debug:router']);
        $this->injectApplication($tool, $application);

        $output = ($tool)('debug:router', '{}');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame(0, $data['data']['exitCode']);
        static::assertStringContainsString('Success output', $data['data']['output']);
        static::assertStringNotContainsString("\033[", $data['data']['output']);
        static::assertArrayHasKey('durationMs', $data['data']);
    }

    public function testSuccessPathWithCustomArguments(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $application = $this->createMock(Application::class);
        $application->method('run')
            ->willReturnCallback(function (InputInterface $input, OutputInterface $output): int {
                $output->writeln('formatted');

                return 0;
            });

        $tool = new ConsoleCommandTool($kernel, ['debug:router']);
        $this->injectApplication($tool, $application);

        $output = ($tool)('debug:router', '{"--format": "json"}');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
    }

    public function testReturnsErrorWhenApplicationRunThrows(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $application = $this->createMock(Application::class);
        $application->method('run')->willThrowException(new \RuntimeException('Command failed'));

        $tool = new ConsoleCommandTool($kernel, ['debug:router']);
        $this->injectApplication($tool, $application);

        $output = ($tool)('debug:router', '{}');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertSame('Command failed', $data['error']);
    }

    private function injectApplication(ConsoleCommandTool $tool, Application $application): void
    {
        $ref = new \ReflectionProperty(ConsoleCommandTool::class, 'application');
        $ref->setValue($tool, $application);
    }
}
