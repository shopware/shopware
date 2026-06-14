<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppMcpPrompt\AppMcpPromptCollection;
use Shopware\Core\Framework\App\Aggregate\AppMcpPrompt\AppMcpPromptEntity;
use Shopware\Core\Framework\App\Lifecycle\Persister\AbstractMcpCapabilityPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpPromptPersister;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Mcp\Xml\McpPrompt;
use Shopware\Core\Framework\App\Mcp\Xml\McpPrompts;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(McpPromptPersister::class)]
#[CoversClass(AbstractMcpCapabilityPersister::class)]
#[Package('framework')]
class McpPromptPersisterTest extends TestCase
{
    /**
     * @var EntityRepository<AppMcpPromptCollection>&MockObject
     */
    private EntityRepository&MockObject $mcpPromptRepository;

    private McpPromptPersister $persister;

    private Context $context;

    protected function setUp(): void
    {
        $this->mcpPromptRepository = $this->createMock(EntityRepository::class);
        $this->persister = new McpPromptPersister($this->mcpPromptRepository);
        $this->context = Context::createDefaultContext();
    }

    public function testUpdatePromptsWithNullMcpDeletesExistingPrompts(): void
    {
        $existingEntity = new AppMcpPromptEntity();
        $existingEntity->setId('existing-prompt-id');
        $existingEntity->setName('order-context');
        $existingEntity->setUrl('https://app.example.com/mcp/prompt/order-context');
        $existingEntity->setAppId('app-id');

        $collection = new AppMcpPromptCollection([$existingEntity]);
        $searchResult = new EntitySearchResult(
            AppMcpPromptEntity::class,
            1,
            $collection,
            null,
            new Criteria(),
            $this->context,
        );

        $this->mcpPromptRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->mcpPromptRepository->expects($this->never())->method('upsert');

        $this->mcpPromptRepository->expects($this->once())
            ->method('delete')
            ->with([['id' => 'existing-prompt-id']], $this->context);

        $this->persister->persist(null, 'app-id', 'en-GB', $this->context);
    }

    public function testUpdatePromptsWithMatchingExistingPromptCallsUpsertWithId(): void
    {
        $existingEntity = new AppMcpPromptEntity();
        $existingEntity->setId('existing-prompt-id');
        $existingEntity->setName('order-context');
        $existingEntity->setUrl('https://app.example.com/mcp/prompt/order-context');
        $existingEntity->setAppId('app-id');

        $collection = new AppMcpPromptCollection([$existingEntity]);
        $searchResult = new EntitySearchResult(
            AppMcpPromptEntity::class,
            1,
            $collection,
            null,
            new Criteria(),
            $this->context,
        );

        $prompt = McpPrompt::fromArray([
            'name' => 'order-context',
            'url' => 'https://app.example.com/mcp/prompt/order-context',
            'label' => ['en-GB' => 'Order Context'],
            'description' => [],
        ]);
        $mcpPrompts = McpPrompts::fromArray(['prompts' => [$prompt]]);
        $mcp = $this->createMcpWithPrompts($mcpPrompts);

        $this->mcpPromptRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->mcpPromptRepository->expects($this->once())
            ->method('upsert')
            ->with(
                static::callback(function (array $upserts): bool {
                    static::assertCount(1, $upserts);
                    static::assertSame('existing-prompt-id', $upserts[0]['id']);
                    static::assertSame('order-context', $upserts[0]['name']);
                    static::assertSame('app-id', $upserts[0]['appId']);

                    return true;
                }),
                $this->context,
            );

        $this->mcpPromptRepository->expects($this->never())->method('delete');

        $this->persister->persist($mcp, 'app-id', 'en-GB', $this->context);
    }

    public function testUpdatePromptsWithNewPromptCallsUpsertWithoutId(): void
    {
        $searchResult = new EntitySearchResult(
            AppMcpPromptEntity::class,
            0,
            new AppMcpPromptCollection([]),
            null,
            new Criteria(),
            $this->context,
        );

        $prompt = McpPrompt::fromArray([
            'name' => 'new-prompt',
            'url' => 'https://app.example.com/mcp/prompt/new',
            'label' => ['en-GB' => 'New Prompt'],
            'description' => [],
        ]);
        $mcpPrompts = McpPrompts::fromArray(['prompts' => [$prompt]]);
        $mcp = $this->createMcpWithPrompts($mcpPrompts);

        $this->mcpPromptRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->mcpPromptRepository->expects($this->once())
            ->method('upsert')
            ->with(
                static::callback(function (array $upserts): bool {
                    static::assertCount(1, $upserts);
                    static::assertArrayNotHasKey('id', $upserts[0]);
                    static::assertSame('new-prompt', $upserts[0]['name']);
                    static::assertSame('app-id', $upserts[0]['appId']);

                    return true;
                }),
                $this->context,
            );

        $this->mcpPromptRepository->expects($this->never())->method('delete');

        $this->persister->persist($mcp, 'app-id', 'en-GB', $this->context);
    }

    private function createMcpWithPrompts(McpPrompts $mcpPrompts): Mcp
    {
        $mcp = $this->createMock(Mcp::class);
        $mcp->method('getPrompts')->willReturn($mcpPrompts);

        return $mcp;
    }
}
