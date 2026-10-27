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
        $configService->expects($this->once())->method('get')
            ->willReturnCallback(function (string $key, ?string $salesChannelId = null): string {
                static::assertSame('core.listing.defaultSorting', $key);
                static::assertNull($salesChannelId);

                return 'name-asc';
            });

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new SystemConfigReadTool($configService, $contextProvider);
        $output = ($tool)('core.listing.defaultSorting');

        static::assertFalse($output->isError());
        static::assertSame('core.listing.defaultSorting', $output->data['key']);
        static::assertSame('name-asc', $output->data['value']);
    }

    public function testReadDomain(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->once())->method('getDomain')
            ->willReturnCallback(function (string $domain, ?string $salesChannelId = null, bool $inherit = false): array {
                static::assertSame('core.listing', $domain);
                static::assertNull($salesChannelId);

                return [
                    'core.listing.defaultSorting' => 'name-asc',
                    'core.listing.productsPerPage' => 24,
                ];
            });

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new SystemConfigReadTool($configService, $contextProvider);
        $output = ($tool)('core.listing');

        static::assertFalse($output->isError());
        static::assertSame('core.listing', $output->data['domain']);
        static::assertCount(2, $output->data['values']);
    }

    public function testReadWithSalesChannelId(): void
    {
        $salesChannelId = 'abc123';
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->once())->method('get')
            ->willReturnCallback(function (string $key, ?string $sc = null) use ($salesChannelId): string {
                static::assertSame('core.listing.defaultSorting', $key);
                static::assertSame($salesChannelId, $sc);

                return 'price-asc';
            });

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new SystemConfigReadTool($configService, $contextProvider);
        $output = ($tool)('core.listing.defaultSorting', $salesChannelId);

        static::assertFalse($output->isError());
        static::assertSame('price-asc', $output->data['value']);
    }

    public function testNoDotTreatedAsDomain(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->once())->method('getDomain')
            ->with('core', null)
            ->willReturn(['core.listing.defaultSorting' => 'name-asc']);

        $configService->expects($this->never())->method('get');

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new SystemConfigReadTool($configService, $contextProvider);
        $output = ($tool)('core');

        static::assertFalse($output->isError());
        static::assertSame('core', $output->data['domain']);
        static::assertCount(1, $output->data['values']);
    }

    public function testAclDenied(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->never())->method('get');
        $configService->expects($this->never())->method('getDomain');

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new SystemConfigReadTool($configService, $contextProvider);
        $output = ($tool)('core.listing.defaultSorting');

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('system_config:read', $output->error->message);
    }
}
