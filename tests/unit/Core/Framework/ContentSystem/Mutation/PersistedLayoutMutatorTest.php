<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\PersistedLayoutMutator;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * @internal
 */
#[CoversClass(PersistedLayoutMutator::class)]
class PersistedLayoutMutatorTest extends TestCase
{
    private const VERSION = '2026-06-22T10:00:00.000+00:00';

    #[TestDox('persists an orphaning mutation and reports the detached subtree for re-attachment')]
    public function testPersistsOrphaningMutationAndReportsOrphans(): void
    {
        $id = Uuid::randomHex();
        $repository = $this->staticRepository($this->entity($id, null));

        $orphaning = $this->orphaningMutation('detached-child');

        $mutator = new PersistedLayoutMutator($this->lockFactory(), $repository, $this->elementSerializer(), $this->registry(), $this->diagnostics());

        $result = $mutator->mutate($id, null, $orphaning, Context::createDefaultContext());

        static::assertCount(1, $result->orphaned);
        static::assertSame('detached-child', $result->orphaned[0]->getId());
    }

    #[TestDox('persists the mutated tree to the repository')]
    public function testPersistsMutatedTreeToRepository(): void
    {
        $id = Uuid::randomHex();
        $repository = $this->staticRepository($this->entity($id, self::VERSION));

        $mutator = new PersistedLayoutMutator($this->lockFactory(), $repository, $this->elementSerializer(), $this->registry(), $this->diagnostics());

        $mutator->mutate($id, self::VERSION, new RemoveElement('block-a'), Context::createDefaultContext());

        static::assertSame($id, $repository->updates[0][0]['id']);
        static::assertCount(1, $repository->updates[0][0]['layout']);
    }

    #[TestDox('returns the re-resolved layout after mutation')]
    public function testReturnsReResolvedLayoutAfterMutation(): void
    {
        $id = Uuid::randomHex();
        $repository = $this->staticRepository($this->entity($id, self::VERSION));

        $mutator = new PersistedLayoutMutator($this->lockFactory(), $repository, $this->elementSerializer(), $this->registry(), $this->diagnostics());

        $result = $mutator->mutate($id, self::VERSION, new RemoveElement('block-a'), Context::createDefaultContext());

        static::assertSame(['block-b'], array_map(static fn (ContentElement $e): string => $e->getId(), $result->layout));
    }

    #[DataProvider('diagnosesAgainstRootSourceProvider')]
    #[TestDox('diagnoses the mutated tree against the context resolved from the layouts root source ($_dataName)')]
    public function testDiagnosesAgainstResolvedRootSource(string $rootSource, bool $rooted): void
    {
        $id = Uuid::randomHex();
        $repository = $this->staticRepository($this->entity($id, null, $rootSource));

        $rootContext = $rooted ? [$this->providedContext()] : [];

        $registry = $this->createMock(RootSourceRegistry::class);
        $registry->expects($this->once())->method('resolve')->with($rootSource)->willReturn($rootContext);

        $diagnostics = $this->createMock(LayoutDiagnostics::class);
        $diagnostics->expects($this->once())
            ->method('analyze')
            ->with(static::anything(), static::identicalTo($rootContext), static::anything())
            ->willReturn(new LayoutAnalysis(new DiagnosticsReport([]), []));

        $mutator = new PersistedLayoutMutator($this->lockFactory(), $repository, $this->elementSerializer(), $registry, $diagnostics);

        $mutator->mutate($id, null, new RemoveElement('block-a'), Context::createDefaultContext());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function diagnosesAgainstRootSourceProvider(): iterable
    {
        yield 'an entity root source threads its resolved root-ambient context' => ['product', true];
        yield 'a none-rooted layout threads an empty context, never a null context' => ['none', false];
    }

    #[TestDox('accepts a token that matches updatedAt to the millisecond, ignoring sub-millisecond noise')]
    public function testAcceptsTokenMatchingToTheMillisecond(): void
    {
        $id = Uuid::randomHex();
        $repository = $this->staticRepository($this->entity($id, '2026-06-22T10:00:00.123456+00:00'));

        $mutator = new PersistedLayoutMutator($this->lockFactory(), $repository, $this->elementSerializer(), $this->registry(), $this->diagnostics());

        $result = $mutator->mutate($id, '2026-06-22T10:00:00.123000+00:00', new RemoveElement('block-a'), Context::createDefaultContext());

        static::assertSame(['block-b'], array_map(static fn (ContentElement $e): string => $e->getId(), $result->layout));
    }

    #[TestDox('accepts a null expected version for a never-updated layout')]
    public function testAcceptsNullVersionForNeverUpdatedLayout(): void
    {
        $id = Uuid::randomHex();
        $repository = $this->staticRepository($this->entity($id, null));

        $mutator = new PersistedLayoutMutator($this->lockFactory(), $repository, $this->elementSerializer(), $this->registry(), $this->diagnostics());

        $result = $mutator->mutate($id, null, new RemoveElement('block-a'), Context::createDefaultContext());

        static::assertSame(['block-b'], array_column(array_map(static fn (ContentElement $e): array => ['id' => $e->getId()], $result->layout), 'id'));
    }

    #[TestDox('throws layoutNotFound and never writes when the layout does not exist')]
    public function testThrowsWhenLayoutDoesNotExist(): void
    {
        $id = Uuid::randomHex();

        $this->assertMutateThrowsWithoutWriting($id, null, null, ContentSystemException::contentLayoutNotFound($id));
    }

    /**
     * @param ?string $committedUpdatedAt the row's stored updatedAt
     * @param string $token the optimistic-concurrency token the caller passes
     */
    #[DataProvider('rejectsVersionConflictProvider')]
    #[TestDox('throws layoutVersionConflict and never writes when the token does not match updatedAt ($_dataName)')]
    public function testRejectsVersionConflictWithoutWriting(?string $committedUpdatedAt, string $token): void
    {
        $id = Uuid::randomHex();

        $this->assertMutateThrowsWithoutWriting(
            $id,
            $this->entity($id, $committedUpdatedAt),
            $token,
            ContentSystemException::layoutVersionConflict($id),
        );
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function rejectsVersionConflictProvider(): iterable
    {
        yield 'a stale token older than the committed updatedAt' => [self::VERSION, '2020-01-01T00:00:00.000+00:00'];
        yield 'a token differing from updatedAt at the millisecond' => ['2026-06-22T10:00:00.123000+00:00', '2026-06-22T10:00:00.456000+00:00'];
        yield 'a non-null token for a never-updated layout' => [null, '2026-01-01T00:00:00.000+00:00'];
    }

    #[TestDox('rejects an unparseable expected version token with a 400 without writing')]
    public function testRejectsUnparseableVersionTokenWithoutWriting(): void
    {
        $id = Uuid::randomHex();

        $this->assertMutateThrowsWithoutWriting(
            $id,
            $this->entity($id, self::VERSION),
            'not-a-date',
            ContentSystemException::invalidVersionToken('not-a-date'),
        );
    }

    #[TestDox('propagates a WriteException from the committing write without swallowing it')]
    public function testPropagatesWriteGateRejection(): void
    {
        $id = Uuid::randomHex();
        $repository = $this->repository($this->entity($id, null));

        $writeException = (new WriteException())->add(new \RuntimeException('binding broke resolvability'));
        $repository->method('update')->willThrowException($writeException);

        $mutator = new PersistedLayoutMutator($this->lockFactory(), $repository, $this->elementSerializer(), $this->registry(), $this->diagnostics());

        $this->expectExceptionObject($writeException);

        $mutator->mutate($id, null, new RemoveElement('block-a'), Context::createDefaultContext());
    }

    /**
     * Asserts mutate() throws the expected exception and commits no write, reading the absence of a write off the
     * StaticEntityRepository's recorded updates rather than a mock interaction.
     */
    private function assertMutateThrowsWithoutWriting(
        string $layoutId,
        ?ContentLayoutEntity $entity,
        ?string $token,
        ContentSystemException $expected,
    ): void {
        $repository = $this->staticRepository($entity);
        $mutator = new PersistedLayoutMutator($this->lockFactory(), $repository, $this->elementSerializer(), $this->registry(), $this->diagnostics());

        try {
            $mutator->mutate($layoutId, $token, new RemoveElement('block-a'), Context::createDefaultContext());
            static::fail('Expected a ' . $expected->getErrorCode() . ' exception, but none was thrown.');
        } catch (ContentSystemException $exception) {
            static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
            static::assertSame($expected->getMessage(), $exception->getMessage());
        }

        static::assertSame([], $repository->updates);
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>&MockObject
     */
    private function repository(?ContentLayoutEntity $entity): EntityRepository
    {
        $context = Context::createDefaultContext();
        $collection = new ContentLayoutCollection($entity === null ? [] : [$entity]);
        $searchResult = new EntitySearchResult('content_layout', $collection->count(), $collection, null, new Criteria(), $context);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($searchResult);

        return $repository;
    }

    /**
     * @return StaticEntityRepository<ContentLayoutCollection>
     */
    private function staticRepository(?ContentLayoutEntity $entity): StaticEntityRepository
    {
        $collection = new ContentLayoutCollection($entity === null ? [] : [$entity]);

        /** @var StaticEntityRepository<ContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([$collection]);

        return $repository;
    }

    private function entity(string $id, ?string $updatedAt, string $rootSource = 'product'): ContentLayoutEntity
    {
        $entity = new ContentLayoutEntity();
        $entity->setId($id);
        $entity->setUniqueIdentifier($id);
        $entity->setRootSource($rootSource);
        $entity->setLayout([new ContentElement('block-a', 'Sw:Card'), new ContentElement('block-b', 'Sw:Card')]);

        if ($updatedAt !== null) {
            $entity->setUpdatedAt(new \DateTimeImmutable($updatedAt));
        }

        return $entity;
    }

    private function lockFactory(): LockFactory
    {
        return new LockFactory(new InMemoryStore());
    }

    private function registry(): RootSourceRegistry
    {
        $registry = static::createStub(RootSourceRegistry::class);
        $registry->method('resolve')->willReturn([]);

        return $registry;
    }

    private function diagnostics(): LayoutDiagnostics
    {
        $diagnostics = static::createStub(LayoutDiagnostics::class);
        $diagnostics->method('analyze')->willReturn(new LayoutAnalysis(new DiagnosticsReport([]), []));

        return $diagnostics;
    }

    private function elementSerializer(): ContentElementFieldSerializer
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('serializeContentElement')->willReturnCallback(
            static fn (ContentElement $element): array => ['id' => $element->getId(), 'component' => $element->getComponent(), 'properties' => []],
        );

        return $serializer;
    }

    private function providedContext(): ProvidedContext
    {
        return new ProvidedContext(
            contextKey: 'product',
            fqcn: \stdClass::class,
            contextType: ContextType::Single,
            providerElementId: null,
            distribution: DistributionStrategy::Broadcast,
        );
    }

    private function orphaningMutation(string $orphanId): LayoutMutation
    {
        return new class($orphanId) implements LayoutMutation {
            public function __construct(private readonly string $orphanId)
            {
            }

            public function apply(array $tree): array
            {
                return $tree;
            }

            public function affected(): array
            {
                return [];
            }

            public function orphaned(): array
            {
                return [new ContentElement($this->orphanId, 'Sw:Block')];
            }

            public function droppedWiring(): array
            {
                return [];
            }

            public function droppedProperties(): array
            {
                return [];
            }
        };
    }
}
