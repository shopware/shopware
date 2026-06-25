<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolCollection;
use Shopware\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Persister\AbstractMcpCapabilityPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpToolPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Mcp\Xml\McpTool;
use Shopware\Core\Framework\App\Mcp\Xml\McpTools;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(McpToolPersister::class)]
#[CoversClass(AbstractMcpCapabilityPersister::class)]
#[Package('framework')]
class McpToolPersisterTest extends TestCase
{
    /**
     * @var EntityRepository<AppMcpToolCollection>&MockObject
     */
    private EntityRepository&MockObject $mcpToolRepository;

    private McpToolPersister $persister;

    private Context $context;

    protected function setUp(): void
    {
        $this->mcpToolRepository = $this->createMock(EntityRepository::class);
        $this->persister = new McpToolPersister($this->mcpToolRepository);
        $this->context = Context::createDefaultContext();
    }

    public function testUpdateToolsWithNullMcpDeletesExistingTools(): void
    {
        $existingEntity = new AppMcpToolEntity();
        $existingEntity->setId('existing-tool-id');
        $existingEntity->setName('sync-orders');
        $existingEntity->setUrl('https://app.example.com/mcp/sync');
        $existingEntity->setAppId('app-id');

        $collection = new AppMcpToolCollection([$existingEntity]);
        $searchResult = new EntitySearchResult(
            AppMcpToolEntity::class,
            1,
            $collection,
            null,
            new Criteria(),
            $this->context,
        );

        $this->mcpToolRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->mcpToolRepository->expects($this->never())->method('upsert');

        $this->mcpToolRepository->expects($this->once())
            ->method('delete')
            ->with([['id' => 'existing-tool-id']], $this->context);

        $this->persister->persist(null, 'app-id', 'en-GB', $this->context);
    }

    public function testUpdateToolsWithMatchingExistingToolCallsUpsertWithId(): void
    {
        $existingEntity = new AppMcpToolEntity();
        $existingEntity->setId('existing-tool-id');
        $existingEntity->setName('sync-orders');
        $existingEntity->setUrl('https://app.example.com/mcp/sync');
        $existingEntity->setAppId('app-id');

        $collection = new AppMcpToolCollection([$existingEntity]);
        $searchResult = new EntitySearchResult(
            AppMcpToolEntity::class,
            1,
            $collection,
            null,
            new Criteria(),
            $this->context,
        );

        $tool = McpTool::fromArray([
            'name' => 'sync-orders',
            'url' => 'https://app.example.com/mcp/sync',
            'label' => ['en-GB' => 'Sync Orders'],
            'description' => [],
        ]);
        $mcpTools = McpTools::fromArray(['tools' => [$tool]]);
        $mcp = $this->createMcpWithTools($mcpTools);

        $this->mcpToolRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->mcpToolRepository->expects($this->once())
            ->method('upsert')
            ->with(
                static::callback(function (array $upserts): bool {
                    static::assertCount(1, $upserts);
                    static::assertSame('existing-tool-id', $upserts[0]['id']);
                    static::assertSame('sync-orders', $upserts[0]['name']);
                    static::assertSame('app-id', $upserts[0]['appId']);

                    return true;
                }),
                $this->context,
            );

        $this->mcpToolRepository->expects($this->never())->method('delete');

        $this->persister->persist($mcp, 'app-id', 'en-GB', $this->context);
    }

    public function testUpdateToolsWithNewToolCallsUpsertWithoutId(): void
    {
        $searchResult = new EntitySearchResult(
            AppMcpToolEntity::class,
            0,
            new AppMcpToolCollection([]),
            null,
            new Criteria(),
            $this->context,
        );

        $tool = McpTool::fromArray([
            'name' => 'new-tool',
            'url' => 'https://app.example.com/mcp/new',
            'label' => ['en-GB' => 'New Tool'],
            'description' => [],
        ]);
        $mcpTools = McpTools::fromArray(['tools' => [$tool]]);
        $mcp = $this->createMcpWithTools($mcpTools);

        $this->mcpToolRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->mcpToolRepository->expects($this->once())
            ->method('upsert')
            ->with(
                static::callback(function (array $upserts): bool {
                    static::assertCount(1, $upserts);
                    static::assertArrayNotHasKey('id', $upserts[0]);
                    static::assertSame('new-tool', $upserts[0]['name']);
                    static::assertSame('app-id', $upserts[0]['appId']);

                    return true;
                }),
                $this->context,
            );

        $this->mcpToolRepository->expects($this->never())->method('delete');

        $this->persister->persist($mcp, 'app-id', 'en-GB', $this->context);
    }

    public function testValidateRequiredPrivilegesPassesWhenPrivilegesMatchManifest(): void
    {
        $tool = McpTool::fromArray([
            'name' => 'my-tool',
            'url' => '/api/script/my-tool',
            'label' => ['en-GB' => 'My Tool'],
            'description' => [],
            'requiredPrivileges' => ['product:read'],
        ]);
        $mcp = $this->createMcpWithTools(McpTools::fromArray(['tools' => [$tool]]));

        $manifest = $this->createManifest('MyApp', Permissions::fromArray([
            'permissions' => ['product' => ['read']],
            'additionalPrivileges' => [],
        ]));

        $this->expectNotToPerformAssertions();
        $this->persister->validateRequiredPrivileges($manifest, $mcp);
    }

    public function testValidateRequiredPrivilegesThrowsWhenPrivilegeNotInManifest(): void
    {
        $tool = McpTool::fromArray([
            'name' => 'my-tool',
            'url' => '/api/script/my-tool',
            'label' => ['en-GB' => 'My Tool'],
            'description' => [],
            'requiredPrivileges' => ['order:read'],
        ]);
        $mcp = $this->createMcpWithTools(McpTools::fromArray(['tools' => [$tool]]));

        $manifest = $this->createManifest('MyApp', Permissions::fromArray([
            'permissions' => ['product' => ['read']],
            'additionalPrivileges' => [],
        ]));

        $this->expectException(AppException::class);
        $this->persister->validateRequiredPrivileges($manifest, $mcp);
    }

    public function testValidateRequiredPrivilegesSkipsWhenNoManifestPermissions(): void
    {
        $tool = McpTool::fromArray([
            'name' => 'my-tool',
            'url' => '/api/script/my-tool',
            'label' => ['en-GB' => 'My Tool'],
            'description' => [],
            'requiredPrivileges' => ['order:read'],
        ]);
        $mcp = $this->createMcpWithTools(McpTools::fromArray(['tools' => [$tool]]));

        $manifest = $this->createManifest('MyApp', null);

        $this->expectNotToPerformAssertions();
        $this->persister->validateRequiredPrivileges($manifest, $mcp);
    }

    public function testValidateRequiredPrivilegesSkipsToolsWithoutRequirements(): void
    {
        $tool = McpTool::fromArray([
            'name' => 'my-tool',
            'url' => '/api/script/my-tool',
            'label' => ['en-GB' => 'My Tool'],
            'description' => [],
        ]);
        $mcp = $this->createMcpWithTools(McpTools::fromArray(['tools' => [$tool]]));

        $manifest = $this->createManifest('MyApp', Permissions::fromArray([
            'permissions' => ['product' => ['read']],
            'additionalPrivileges' => [],
        ]));

        $this->expectNotToPerformAssertions();
        $this->persister->validateRequiredPrivileges($manifest, $mcp);
    }

    public function testValidateRequiredPrivilegesSkipsWhenNullMcp(): void
    {
        $manifest = $this->createManifest('MyApp', Permissions::fromArray([
            'permissions' => ['product' => ['read']],
            'additionalPrivileges' => [],
        ]));

        $this->expectNotToPerformAssertions();
        $this->persister->validateRequiredPrivileges($manifest, null);
    }

    private function createMcpWithTools(McpTools $mcpTools): Mcp
    {
        $mcp = $this->createMock(Mcp::class);
        $mcp->method('getTools')->willReturn($mcpTools);

        return $mcp;
    }

    private function createManifest(string $appName, ?Permissions $permissions): Manifest
    {
        $metadata = Metadata::fromArray([
            'label' => ['en-GB' => $appName],
            'description' => [],
            'name' => $appName,
            'author' => 'shopware AG',
            'copyright' => '(c) shopware AG',
            'license' => 'MIT',
            'version' => '1.0.0',
            'privacyPolicyExtensions' => [],
        ]);

        $manifest = $this->createMock(Manifest::class);
        $manifest->method('getMetadata')->willReturn($metadata);
        $manifest->method('getPermissions')->willReturn($permissions);

        return $manifest;
    }
}
