<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolCollection;
use Shopware\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolDefinition;
use Shopware\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolEntity;
use Shopware\Core\Framework\App\Lifecycle\Persister\AbstractMcpCapabilityPersister;
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
 *
 * Exercises the shared persist flow through McpToolPersister, the lightest concrete subclass.
 */
#[Package('framework')]
#[CoversClass(AbstractMcpCapabilityPersister::class)]
class AbstractMcpCapabilityPersisterTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
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
            AppMcpToolDefinition::ENTITY_NAME,
            1,
            $collection,
            null,
            new Criteria(),
            $this->context,
        );

        $mcpToolRepository = $this->createMock(EntityRepository::class);
        $mcpToolRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $mcpToolRepository->expects($this->never())->method('upsert');

        $mcpToolRepository->expects($this->once())
            ->method('delete')
            ->with([['id' => 'existing-tool-id']], $this->context);

        $persister = $this->createPersister($mcpToolRepository);
        $persister->persist(null, 'app-id', 'en-GB', $this->context);
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
            AppMcpToolDefinition::ENTITY_NAME,
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

        $mcpToolRepository = $this->createMock(EntityRepository::class);
        $mcpToolRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $mcpToolRepository->expects($this->once())
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

        $mcpToolRepository->expects($this->never())->method('delete');

        $persister = $this->createPersister($mcpToolRepository);
        $persister->persist($mcp, 'app-id', 'en-GB', $this->context);
    }

    public function testUpdateToolsWithNewToolCallsUpsertWithoutId(): void
    {
        $searchResult = new EntitySearchResult(
            AppMcpToolDefinition::ENTITY_NAME,
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

        $mcpToolRepository = $this->createMock(EntityRepository::class);
        $mcpToolRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $mcpToolRepository->expects($this->once())
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

        $mcpToolRepository->expects($this->never())->method('delete');

        $persister = $this->createPersister($mcpToolRepository);
        $persister->persist($mcp, 'app-id', 'en-GB', $this->context);
    }

    /**
     * @param EntityRepository<AppMcpToolCollection>&MockObject $mcpToolRepository
     */
    private function createPersister(EntityRepository&MockObject $mcpToolRepository): McpToolPersister
    {
        return new McpToolPersister($mcpToolRepository);
    }

    private function createMcpWithTools(McpTools $mcpTools): Mcp
    {
        $mcp = static::createStub(Mcp::class);
        $mcp->method('getTools')->willReturn($mcpTools);

        return $mcp;
    }
}
