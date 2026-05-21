<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemConfigWriteTool::class)]
class SystemConfigWriteToolTest extends TestCase
{
    public function testDryRunReturnsPreviewWithoutCallingSet(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')->with('core.test.key', null)->willReturn('old-value');
        $configService->expects($this->never())->method('set');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new SystemConfigWriteTool($configService, $contextProvider);
        $output = ($tool)('core.test.key', '"new-value"');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertTrue($data['_meta']['dryRun']);
        static::assertSame('old-value', $data['data']['oldValue']);
        static::assertSame('new-value', $data['data']['newValue']);
    }

    public function testNonDryRunCallsSetAndReturnsSuccess(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')->with('core.test.key', null)->willReturn('old-value');
        $configService->expects($this->once())->method('set')->with('core.test.key', 'new-value', null);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new SystemConfigWriteTool($configService, $contextProvider);
        $output = ($tool)('core.test.key', '"new-value"', null, false);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertFalse($data['_meta']['dryRun']);
    }

    public function testJsonValueDecoding(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')->willReturn(false);
        $configService->expects($this->once())->method('set')->with('core.bool.key', true, null);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new SystemConfigWriteTool($configService, $contextProvider);
        $output = ($tool)('core.bool.key', 'true', null, false);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['data']['newValue']);
    }

    public function testNonJsonValueIsUsedAsString(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')->willReturn(null);
        $configService->expects($this->once())->method('set')->with('core.text.key', 'plain text value', null);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new SystemConfigWriteTool($configService, $contextProvider);
        $output = ($tool)('core.text.key', 'plain text value', null, false);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame('plain text value', $data['data']['newValue']);
    }

    public function testNullJsonValueReturnsError(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->never())->method('set');
        $configService->expects($this->never())->method('get');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new SystemConfigWriteTool($configService, $contextProvider);
        $output = ($tool)('core.test.key', 'null', null, false);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Setting null is not supported via MCP', $data['error']);
    }

    public function testDeniesAccessWithoutUpdatePermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->never())->method('set');

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new SystemConfigWriteTool($configService, $contextProvider);
        $output = ($tool)('core.test.key', '"value"');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('system_config:update', $data['error']);
    }

    public function testWriteWithSalesChannelId(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')->with('core.test.key', 'sc-1')->willReturn('old');
        $configService->expects($this->once())->method('set')->with('core.test.key', 'new', 'sc-1');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new SystemConfigWriteTool($configService, $contextProvider);
        $output = ($tool)('core.test.key', '"new"', 'sc-1', false);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('sc-1', $data['_meta']['salesChannelId']);
    }
}
