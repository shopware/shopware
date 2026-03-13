<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolCollection;
use Shopware\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolEntity;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpToolPersister;
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

        $this->persister->updateTools(null, 'app-id', 'en-GB', $this->context);
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

        $this->persister->updateTools($mcp, 'app-id', 'en-GB', $this->context);
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

        $this->persister->updateTools($mcp, 'app-id', 'en-GB', $this->context);
    }

    private function createMcpWithTools(McpTools $mcpTools): Mcp
    {
        $reflection = new \ReflectionClass(Mcp::class);

        /** @var Mcp $instance */
        $instance = $reflection->newInstanceWithoutConstructor();

        $pathProp = $reflection->getProperty('path');
        $pathProp->setValue($instance, '/path');

        $toolsProp = $reflection->getProperty('tools');
        $toolsProp->setValue($instance, $mcpTools);

        return $instance;
    }
}
