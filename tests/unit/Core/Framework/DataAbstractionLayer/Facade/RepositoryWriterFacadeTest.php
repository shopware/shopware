<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncResult;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacade;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\AppContextFactory;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RepositoryWriterFacade::class)]
class RepositoryWriterFacadeTest extends TestCase
{
    /**
     * @param \Closure(RepositoryWriterFacade): EntityWrittenContainerEvent $write
     */
    #[DataProvider('writeMethodProvider')]
    public function testWriteUsesAFreshAppContextPerCall(\Closure $write): void
    {
        $integrationId = Uuid::randomHex();
        $hookContext = Context::createDefaultContext();
        $hookContext->setRuleIds([Uuid::randomHex()]);
        $ruleIds = $hookContext->getRuleIds();

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn(StaticEntityRepository::of(ProductCollection::class));

        $facade = new RepositoryWriterFacade(
            $registry,
            static::createStub(SyncService::class),
            AppContextFactory::forApp($hookContext, $integrationId, ['product:create']),
        );

        $first = $write($facade)->getContext();
        $first->setRuleIds([Uuid::randomHex()]);
        $second = $write($facade)->getContext();

        static::assertNotSame($first, $second);
        static::assertNotSame($hookContext, $first);
        static::assertSame($ruleIds, $second->getRuleIds());

        $source = $second->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);
        static::assertSame($integrationId, $source->getIntegrationId());
    }

    public static function writeMethodProvider(): \Generator
    {
        yield 'upsert' => [static fn (RepositoryWriterFacade $facade): EntityWrittenContainerEvent => $facade->upsert('product', [['id' => Uuid::randomHex()]])];
        yield 'delete' => [static fn (RepositoryWriterFacade $facade): EntityWrittenContainerEvent => $facade->delete('product', [['id' => Uuid::randomHex()]])];
    }

    public function testSyncUsesAFreshAppContextPerCall(): void
    {
        $integrationId = Uuid::randomHex();
        $hookContext = Context::createDefaultContext();
        $hookContext->setRuleIds([Uuid::randomHex()]);
        $ruleIds = $hookContext->getRuleIds();

        /** @var list<Context> $syncedContexts */
        $syncedContexts = [];
        $syncService = $this->createMock(SyncService::class);
        $syncService->expects($this->exactly(2))
            ->method('sync')
            ->willReturnCallback(static function (array $operations, Context $context, SyncBehavior $behavior) use (&$syncedContexts): SyncResult {
                $syncedContexts[] = $context;

                return new SyncResult([]);
            });

        $facade = new RepositoryWriterFacade(
            static::createStub(DefinitionInstanceRegistry::class),
            $syncService,
            AppContextFactory::forApp($hookContext, $integrationId, ['product:update']),
        );

        $payload = [['entity' => 'product', 'action' => 'upsert', 'payload' => [['id' => Uuid::randomHex()]]]];

        $facade->sync($payload);
        $syncedContexts[0]->setRuleIds([Uuid::randomHex()]);
        $facade->sync($payload);

        static::assertCount(2, $syncedContexts);
        static::assertNotSame($syncedContexts[0], $syncedContexts[1]);
        static::assertNotSame($hookContext, $syncedContexts[0]);
        static::assertSame($ruleIds, $syncedContexts[1]->getRuleIds());

        $source = $syncedContexts[1]->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);
        static::assertSame($integrationId, $source->getIntegrationId());
    }
}
