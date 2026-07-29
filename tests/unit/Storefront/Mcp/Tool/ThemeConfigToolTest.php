<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Mcp\Tool\ThemeConfigTool;
use Shopware\Storefront\Theme\ThemeService;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ThemeConfigTool::class)]
class ThemeConfigToolTest extends TestCase
{
    public function testGetReturnsThemeConfig(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $expectedConfig = ['sw-color-brand-primary' => ['value' => '#ff0000']];

        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects($this->once())
            ->method('getPlainThemeConfiguration')
            ->with($themeId)
            ->willReturn($expectedConfig);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($themeId);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            $themeService,
            $contextProvider,
            $connection,
        );

        $output = $tool($salesChannelId, 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame($themeId, $data['data']['themeId']);
        static::assertSame($expectedConfig, $data['data']['config']);
    }

    public function testUpdateDryRunReturnsPreview(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();

        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects($this->never())->method('updateTheme');

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($themeId);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read', 'theme:update']));

        $tool = new ThemeConfigTool(
            $themeService,
            $contextProvider,
            $connection,
        );

        $config = json_encode(['sw-color-brand-primary' => ['value' => '#0000ff']], \JSON_THROW_ON_ERROR);
        $output = $tool($salesChannelId, 'update', $config, true);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertTrue($data['_meta']['dryRun']);
        static::assertSame($themeId, $data['data']['themeId']);
    }

    public function testUpdateCommitCallsThemeService(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $configValues = ['sw-color-brand-primary' => ['value' => '#0000ff']];

        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects($this->once())
            ->method('updateTheme')
            ->with($themeId, $configValues, null);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($themeId);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read', 'theme:update']));

        $tool = new ThemeConfigTool(
            $themeService,
            $contextProvider,
            $connection,
        );

        $config = json_encode($configValues, \JSON_THROW_ON_ERROR);
        $output = $tool($salesChannelId, 'update', $config, false);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertFalse($data['_meta']['dryRun']);
        static::assertSame(['sw-color-brand-primary'], $data['data']['updatedKeys']);
    }

    public function testNoThemeReturnsError(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(false);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            $connection,
        );

        $output = $tool(Uuid::randomHex(), 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('No theme assigned', $data['error']);
    }

    public function testUnknownActionReturnsError(): void
    {
        $themeId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($themeId);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            $connection,
        );

        $output = $tool(Uuid::randomHex(), 'delete');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Unknown action', $data['error']);
    }

    public function testGetExceptionReturnsError(): void
    {
        $themeId = Uuid::randomHex();

        $themeService = static::createStub(ThemeService::class);
        $themeService->method('getPlainThemeConfiguration')
            ->willThrowException(new \RuntimeException('Theme config broken'));

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($themeId);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool($themeService, $contextProvider, $connection);

        $output = $tool(Uuid::randomHex(), 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Theme config broken', $data['error']);
    }

    public function testUpdateWithEmptyConfigReturnsError(): void
    {
        $themeId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($themeId);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read', 'theme:update']));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            $connection,
        );

        $output = $tool(Uuid::randomHex(), 'update', '{}', false);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('non-empty JSON', $data['error']);
    }

    public function testUpdateExceptionReturnsError(): void
    {
        $themeId = Uuid::randomHex();

        $themeService = static::createStub(ThemeService::class);
        $themeService->method('updateTheme')
            ->willThrowException(new \RuntimeException('Compilation failed'));

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($themeId);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read', 'theme:update']));

        $tool = new ThemeConfigTool($themeService, $contextProvider, $connection);

        $config = json_encode(['sw-color-brand-primary' => ['value' => '#ff0000']], \JSON_THROW_ON_ERROR);
        $output = $tool(Uuid::randomHex(), 'update', $config, false);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Compilation failed', $data['error']);
    }

    public function testMalformedConfigJsonReturnsError(): void
    {
        $themeId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($themeId);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read', 'theme:update']));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            $connection,
        );

        $output = $tool(Uuid::randomHex(), 'update', 'not-json', false);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Invalid JSON', $data['error']);
        static::assertStringContainsString('config', $data['error']);
    }

    public function testEmptySalesChannelIdReturnsError(): void
    {
        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            static::createStub(McpContextProvider::class),
            static::createStub(Connection::class),
        );

        $output = $tool('');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('salesChannelId is required', $data['error']);
    }

    public function testMissingAclReturnsError(): void
    {
        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext([]));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            static::createStub(Connection::class),
        );

        $output = $tool(Uuid::randomHex(), 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
    }

    /**
     * @param list<string> $privileges
     */
    private function createAdminContext(array $privileges): Context
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions($privileges);

        return new Context($source);
    }
}
