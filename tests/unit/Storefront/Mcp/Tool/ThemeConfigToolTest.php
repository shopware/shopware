<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpToolAttributeReader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Mcp\Tool\ThemeConfigTool;
use Shopware\Storefront\Theme\ThemeService;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeConfigTool::class)]
class ThemeConfigToolTest extends TestCase
{
    public function testDeclaresExplicitThemeGroup(): void
    {
        // Without an explicit #[McpToolGroup] the group is derived from the tool-name
        // prefix (McpToolAnalysisCompilerPass), which for "shopware-theme-config" would
        // produce the accidental "shopware" toolset. Guard the intended "theme" group.
        $group = McpToolAttributeReader::resolveInfo(ThemeConfigTool::class, McpToolGroup::class, ['group']);

        static::assertNotNull($group);
        static::assertSame('theme', $group['group']);
    }

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

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            $themeService,
            $contextProvider,
            $this->createConnection($themeId, [$salesChannelId]),
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

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read', 'theme:update']));

        $tool = new ThemeConfigTool(
            $themeService,
            $contextProvider,
            $this->createConnection($themeId, [$salesChannelId]),
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

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read', 'theme:update']));

        $tool = new ThemeConfigTool(
            $themeService,
            $contextProvider,
            $this->createConnection($themeId, [$salesChannelId]),
        );

        $config = json_encode($configValues, \JSON_THROW_ON_ERROR);
        $output = $tool($salesChannelId, 'update', $config, false);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertFalse($data['_meta']['dryRun']);
        static::assertSame(['sw-color-brand-primary'], $data['data']['updatedKeys']);
    }

    public function testResolvesSalesChannelByName(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $expectedConfig = ['sw-color-brand-primary' => ['value' => '#ff0000']];

        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects($this->once())
            ->method('getPlainThemeConfiguration')
            ->with($themeId)
            ->willReturn($expectedConfig);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            $themeService,
            $contextProvider,
            $this->createConnection($themeId, [$salesChannelId]),
        );

        $output = $tool('Storefront', 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame($themeId, $data['data']['themeId']);
        static::assertSame($expectedConfig, $data['data']['config']);
    }

    /**
     * Regression guard: before this, a non-UUID input made Uuid::fromHexToBytes() throw out of
     * __invoke(), which the MCP SDK turned into an opaque JSON-RPC -32603 for the client.
     */
    public function testNonUuidInputReturnsCleanErrorInsteadOfThrowing(): void
    {
        $lookupParams = [];

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            $this->createConnection(false, [], ['Storefront'], $lookupParams),
        );

        $output = $tool('not-a-uuid', 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('not-a-uuid', $data['error']);
        // The input never reaches Uuid::fromHexToBytes(); it is bound as a name only.
        static::assertNull($lookupParams['id']);
    }

    public function testUnknownSalesChannelNameListsAvailableNames(): void
    {
        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            $this->createConnection(false, [], ['Storefront', 'Headless']),
        );

        $output = $tool('Storfront', 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('not found', $data['error']);
        static::assertStringContainsString('"Storefront"', $data['error']);
        static::assertStringContainsString('"Headless"', $data['error']);
    }

    public function testAmbiguousSalesChannelNameReturnsError(): void
    {
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            $this->createConnection(false, [$firstId, $secondId]),
        );

        $output = $tool('Storefront', 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('ambiguous', $data['error']);
        static::assertStringContainsString($firstId, $data['error']);
        static::assertStringContainsString($secondId, $data['error']);
    }

    public function testUppercaseUuidIsAccepted(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $lookupParams = [];

        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects($this->once())
            ->method('getPlainThemeConfiguration')
            ->with($themeId)
            ->willReturn([]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            $themeService,
            $contextProvider,
            $this->createConnection($themeId, [$salesChannelId], [], $lookupParams),
        );

        $output = $tool(strtoupper($salesChannelId), 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame($themeId, $data['data']['themeId']);
        // Uuid::isValid() only matches lowercase hex, so without the lowercasing the uppercase
        // input would never be bound as an ID.
        static::assertSame(Uuid::fromHexToBytes($salesChannelId), $lookupParams['id']);
    }

    public function testUnknownSalesChannelUuidReturnsError(): void
    {
        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            $this->createConnection(false, [], ['Storefront']),
        );

        $output = $tool(Uuid::randomHex(), 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('not found', $data['error']);
        static::assertStringNotContainsString('No theme assigned', $data['error']);
    }

    /**
     * Infrastructure failures are not business errors. Per the McpToolResponse contract they must
     * propagate so they get logged server-side, rather than putting driver or schema details from
     * the exception message into a response any caller with theme:read can read.
     */
    public function testDatabaseFailurePropagatesInsteadOfLeakingDetails(): void
    {
        $failure = new \RuntimeException('SQLSTATE[HY000] host db-01 refused');

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willThrowException($failure);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            $connection,
        );

        $this->expectExceptionObject($failure);

        $tool(Uuid::randomHex(), 'get');
    }

    public function testUuidShapedNameIsResolved(): void
    {
        $themeId = Uuid::randomHex();
        $uuidShapedName = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $lookupParams = [];

        $themeService = $this->createMock(ThemeService::class);
        $themeService->expects($this->once())
            ->method('getPlainThemeConfiguration')
            ->with($themeId)
            ->willReturn([]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            $themeService,
            $contextProvider,
            $this->createConnection($themeId, [$salesChannelId], [], $lookupParams),
        );

        $output = $tool($uuidShapedName, 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame($themeId, $data['data']['themeId']);
        // Both candidates are bound in the same lookup, so a UUID that matches no ID cannot
        // shadow a channel carrying it as a name.
        static::assertSame(Uuid::fromHexToBytes($uuidShapedName), $lookupParams['id']);
        static::assertSame($uuidShapedName, $lookupParams['name']);
    }

    public function testErrorReportsNoneWhenShopHasNoSalesChannels(): void
    {
        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            $this->createConnection(false),
        );

        $output = $tool('Storefront', 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Available sales channels: none.', $data['error']);
    }

    public function testAvailableNamesAreTruncated(): void
    {
        $names = array_map(static fn (int $i): string => 'Channel ' . $i, range(1, 25));

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            $this->createConnection(false, [], $names),
        );

        $output = $tool('Unknown', 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('"Channel 20"', $data['error']);
        static::assertStringNotContainsString('"Channel 21"', $data['error']);
        static::assertStringContainsString('and 5 more', $data['error']);
    }

    public function testNoThemeReturnsError(): void
    {
        $salesChannelId = Uuid::randomHex();

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            $this->createConnection(false, [$salesChannelId]),
        );

        $output = $tool($salesChannelId, 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('No theme assigned', $data['error']);
    }

    public function testSurroundingWhitespaceIsTrimmed(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $lookupParams = [];

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            $this->createConnection($themeId, [$salesChannelId], [], $lookupParams),
        );

        $output = $tool('  Storefront  ', 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('Storefront', $lookupParams['name']);
    }

    public function testUnknownActionReturnsError(): void
    {
        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            static::createStub(McpContextProvider::class),
            static::createStub(Connection::class),
        );

        $output = $tool(Uuid::randomHex(), 'delete');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Unknown action', $data['error']);
    }

    public function testGetExceptionReturnsError(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();

        $themeService = static::createStub(ThemeService::class);
        $themeService->method('getPlainThemeConfiguration')
            ->willThrowException(new \RuntimeException('Theme config broken'));

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read']));

        $tool = new ThemeConfigTool(
            $themeService,
            $contextProvider,
            $this->createConnection($themeId, [$salesChannelId]),
        );

        $output = $tool($salesChannelId, 'get');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Theme config broken', $data['error']);
    }

    public function testUpdateWithEmptyConfigReturnsError(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read', 'theme:update']));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            $this->createConnection($themeId, [$salesChannelId]),
        );

        $output = $tool($salesChannelId, 'update', '{}', false);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('non-empty JSON', $data['error']);
    }

    public function testUpdateExceptionReturnsError(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();

        $themeService = static::createStub(ThemeService::class);
        $themeService->method('updateTheme')
            ->willThrowException(new \RuntimeException('Compilation failed'));

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read', 'theme:update']));

        $tool = new ThemeConfigTool(
            $themeService,
            $contextProvider,
            $this->createConnection($themeId, [$salesChannelId]),
        );

        $config = json_encode(['sw-color-brand-primary' => ['value' => '#ff0000']], \JSON_THROW_ON_ERROR);
        $output = $tool($salesChannelId, 'update', $config, false);
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Compilation failed', $data['error']);
    }

    public function testMalformedConfigJsonReturnsError(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($this->createAdminContext(['theme:read', 'theme:update']));

        $tool = new ThemeConfigTool(
            static::createStub(ThemeService::class),
            $contextProvider,
            $this->createConnection($themeId, [$salesChannelId]),
        );

        $output = $tool($salesChannelId, 'update', 'not-json', false);
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
     * The tool issues three queries: the sales channel lookup, the name hint list and the theme
     * lookup. The two column queries are told apart by their parameters rather than their SQL, so
     * the stub survives any rewording of the statements.
     *
     * @param list<string> $resolvedIds sales channel IDs the lookup resolves to
     * @param list<string> $availableNames names offered as a hint when nothing resolves
     * @param array<string, mixed> $lookupParams receives the parameters bound by the lookup
     */
    private function createConnection(
        string|false $themeId,
        array $resolvedIds = [],
        array $availableNames = [],
        array &$lookupParams = [],
    ): Connection {
        $connection = static::createStub(Connection::class);

        // resolveThemeId() is the only remaining fetchOne() caller.
        $connection->method('fetchOne')->willReturn($themeId);

        $connection->method('fetchFirstColumn')->willReturnCallback(
            static function (string $sql, array $params = []) use ($resolvedIds, $availableNames, &$lookupParams): array {
                if ($params === []) {
                    return $availableNames;
                }

                $lookupParams = $params;

                return $resolvedIds;
            }
        );

        return $connection;
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
