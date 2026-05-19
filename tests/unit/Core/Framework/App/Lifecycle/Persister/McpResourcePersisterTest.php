<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppMcpResource\AppMcpResourceCollection;
use Shopware\Core\Framework\App\Aggregate\AppMcpResource\AppMcpResourceEntity;
use Shopware\Core\Framework\App\Lifecycle\Persister\AbstractMcpCapabilityPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpResourcePersister;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Mcp\Xml\McpResource;
use Shopware\Core\Framework\App\Mcp\Xml\McpResources;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(McpResourcePersister::class)]
#[CoversClass(AbstractMcpCapabilityPersister::class)]
#[Package('framework')]
class McpResourcePersisterTest extends TestCase
{
    /**
     * @var EntityRepository<AppMcpResourceCollection>&MockObject
     */
    private EntityRepository&MockObject $mcpResourceRepository;

    private McpResourcePersister $persister;

    private Context $context;

    protected function setUp(): void
    {
        $this->mcpResourceRepository = $this->createMock(EntityRepository::class);
        $this->persister = new McpResourcePersister($this->mcpResourceRepository);
        $this->context = Context::createDefaultContext();
    }

    public function testUpdateResourcesWithNullMcpDeletesExistingResources(): void
    {
        $existingEntity = new AppMcpResourceEntity();
        $existingEntity->setId('existing-resource-id');
        $existingEntity->setName('order-stats');
        $existingEntity->setUri('app://order-stats');
        $existingEntity->setUrl('https://app.example.com/mcp/resource/order-stats');
        $existingEntity->setAppId('app-id');

        $collection = new AppMcpResourceCollection([$existingEntity]);
        $searchResult = new EntitySearchResult(
            AppMcpResourceEntity::class,
            1,
            $collection,
            null,
            new Criteria(),
            $this->context,
        );

        $this->mcpResourceRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->mcpResourceRepository->expects($this->never())->method('upsert');

        $this->mcpResourceRepository->expects($this->once())
            ->method('delete')
            ->with([['id' => 'existing-resource-id']], $this->context);

        $this->persister->persist(null, 'app-id', 'en-GB', $this->context);
    }

    public function testUpdateResourcesWithMatchingExistingResourceCallsUpsertWithId(): void
    {
        $existingEntity = new AppMcpResourceEntity();
        $existingEntity->setId('existing-resource-id');
        $existingEntity->setName('order-stats');
        $existingEntity->setUri('app://order-stats');
        $existingEntity->setUrl('https://app.example.com/mcp/resource/order-stats');
        $existingEntity->setAppId('app-id');

        $collection = new AppMcpResourceCollection([$existingEntity]);
        $searchResult = new EntitySearchResult(
            AppMcpResourceEntity::class,
            1,
            $collection,
            null,
            new Criteria(),
            $this->context,
        );

        $resource = McpResource::fromArray([
            'name' => 'order-stats',
            'uri' => 'app://order-stats',
            'url' => 'https://app.example.com/mcp/resource/order-stats',
            'mimeType' => 'application/json',
            'label' => ['en-GB' => 'Order Stats'],
            'description' => [],
        ]);
        $mcpResources = McpResources::fromArray(['resources' => [$resource]]);
        $mcp = $this->createMcpWithResources($mcpResources);

        $this->mcpResourceRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->mcpResourceRepository->expects($this->once())
            ->method('upsert')
            ->with(
                static::callback(function (array $upserts): bool {
                    static::assertCount(1, $upserts);
                    static::assertSame('existing-resource-id', $upserts[0]['id']);
                    static::assertSame('order-stats', $upserts[0]['name']);
                    static::assertSame('app-id', $upserts[0]['appId']);

                    return true;
                }),
                $this->context,
            );

        $this->mcpResourceRepository->expects($this->never())->method('delete');

        $this->persister->persist($mcp, 'app-id', 'en-GB', $this->context);
    }

    public function testUpdateResourcesWithNewResourceCallsUpsertWithoutId(): void
    {
        $searchResult = new EntitySearchResult(
            AppMcpResourceEntity::class,
            0,
            new AppMcpResourceCollection([]),
            null,
            new Criteria(),
            $this->context,
        );

        $resource = McpResource::fromArray([
            'name' => 'new-resource',
            'uri' => 'app://new-resource',
            'url' => 'https://app.example.com/mcp/resource/new',
            'mimeType' => 'application/json',
            'label' => ['en-GB' => 'New Resource'],
            'description' => [],
        ]);
        $mcpResources = McpResources::fromArray(['resources' => [$resource]]);
        $mcp = $this->createMcpWithResources($mcpResources);

        $this->mcpResourceRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->mcpResourceRepository->expects($this->once())
            ->method('upsert')
            ->with(
                static::callback(function (array $upserts): bool {
                    static::assertCount(1, $upserts);
                    static::assertArrayNotHasKey('id', $upserts[0]);
                    static::assertSame('new-resource', $upserts[0]['name']);
                    static::assertSame('app-id', $upserts[0]['appId']);

                    return true;
                }),
                $this->context,
            );

        $this->mcpResourceRepository->expects($this->never())->method('delete');

        $this->persister->persist($mcp, 'app-id', 'en-GB', $this->context);
    }

    private function createMcpWithResources(McpResources $mcpResources): Mcp
    {
        $mcp = $this->createMock(Mcp::class);
        $mcp->method('getResources')->willReturn($mcpResources);

        return $mcp;
    }
}
