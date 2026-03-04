<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
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

        $tool = new SystemConfigReadTool($configService);
        $output = ($tool)('core.listing.defaultSorting');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('core.listing.defaultSorting', $data['key']);
        static::assertSame('name-asc', $data['value']);
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

        $tool = new SystemConfigReadTool($configService);
        $output = ($tool)('core.listing');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('core.listing', $data['domain']);
        static::assertCount(2, $data['values']);
    }

    public function testReadWithSalesChannelId(): void
    {
        $salesChannelId = 'abc123';
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')
            ->with('core.listing.defaultSorting', $salesChannelId)
            ->willReturn('price-asc');

        $tool = new SystemConfigReadTool($configService);
        $output = ($tool)('core.listing.defaultSorting', $salesChannelId);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('price-asc', $data['value']);
    }
}
