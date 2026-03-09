<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemConfigReadTool::class)]
class SystemConfigReadToolTest extends TestCase
{
    public function testReadSingleKey(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')
            ->with('core.listing.defaultSorting', null)
            ->willReturn('name-asc');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new SystemConfigReadTool($configService, $contextProvider);
        $output = ($tool)('core.listing.defaultSorting');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame('core.listing.defaultSorting', $data['data']['key']);
        static::assertSame('name-asc', $data['data']['value']);
    }

    public function testReadDomain(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('getDomain')
            ->with('core.listing', null)
            ->willReturn([
                'core.listing.defaultSorting' => 'name-asc',
                'core.listing.productsPerPage' => 24,
            ]);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new SystemConfigReadTool($configService, $contextProvider);
        $output = ($tool)('core.listing');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame('core.listing', $data['data']['domain']);
        static::assertCount(2, $data['data']['values']);
    }

    public function testReadWithSalesChannelId(): void
    {
        $salesChannelId = 'abc123';
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')
            ->with('core.listing.defaultSorting', $salesChannelId)
            ->willReturn('price-asc');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new SystemConfigReadTool($configService, $contextProvider);
        $output = ($tool)('core.listing.defaultSorting', $salesChannelId);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame('price-asc', $data['data']['value']);
    }

    public function testNoDotTreatedAsDomain(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('getDomain')
            ->with('core', null)
            ->willReturn(['core.listing.defaultSorting' => 'name-asc']);

        $configService->expects($this->never())->method('get');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new SystemConfigReadTool($configService, $contextProvider);
        $output = ($tool)('core');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame('core', $data['data']['domain']);
        static::assertCount(1, $data['data']['values']);
    }

    public function testAclDenied(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->never())->method('get');
        $configService->expects($this->never())->method('getDomain');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new SystemConfigReadTool($configService, $contextProvider);
        $output = ($tool)('core.listing.defaultSorting');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertArrayHasKey('error', $data);
        static::assertStringContainsString('system_config:read', $data['error']);
    }
}
