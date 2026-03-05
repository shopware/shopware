<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
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

        $tool = new SystemConfigWriteTool($configService);
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

        $tool = new SystemConfigWriteTool($configService);
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

        $tool = new SystemConfigWriteTool($configService);
        $output = ($tool)('core.bool.key', 'true', null, false);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['data']['newValue']);
    }
}
