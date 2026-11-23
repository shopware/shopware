<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PdoException;
use Doctrine\DBAL\Exception\DeadlockException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteContext;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * The memo the layout field serializer leaves on the write `Context` belongs to that one write, retries included,
 * and the next write on the same `Context` replaces it. These cases drive the real write paths, because whether
 * the validator finds the memo depends on which `Context` and `WriteContext` instance each stage sees, and on the
 * DAL dispatching the validation event again when it retries a transaction, which no unit test exercises.
 *
 * @internal
 */
#[Package('framework')]
class ContentLayoutWriteMemoLifetimeTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    /**
     * @var list<mixed>
     */
    private array $encodedLayouts = [];

    private int $layoutValidationAttempts = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('leaves the memo of a sync write carrying several operations owned by that write, with every tree it encoded')]
    public function testAWriteLeavesItsMemoOwnedByThatWrite(): void
    {
        $this->captureEncodedLayouts();
        $context = Context::createDefaultContext();
        $write = WriteContext::createFromContext($context);

        $this->writer()->sync(
            [
                new SyncOperation('first', ContentLayoutDefinition::ENTITY_NAME, SyncOperation::ACTION_UPSERT, [
                    $this->layout('layout-a'),
                    $this->layout('layout-b'),
                ]),
                new SyncOperation('second', ContentLayoutDefinition::ENTITY_NAME, SyncOperation::ACTION_UPSERT, [
                    $this->layout('layout-c'),
                ]),
            ],
            $write
        );

        $memo = $this->memoOf($context);
        static::assertTrue($memo->ownedBy($write));
        static::assertEqualsCanonicalizing(
            [$this->ids->get('layout-a-element'), $this->ids->get('layout-b-element'), $this->ids->get('layout-c-element')],
            $this->memoizedRootIds($memo)
        );
    }

    #[TestDox('replaces the memo of a completed write when the next write on the same context encodes a layout')]
    public function testTheNextWriteOnTheSameContextReplacesTheMemo(): void
    {
        $this->captureEncodedLayouts();
        $context = Context::createDefaultContext();
        $earlierWrite = WriteContext::createFromContext($context);
        $laterWrite = WriteContext::createFromContext($context);

        $this->writer()->upsert($this->definition(), [$this->layout('layout-a')], $earlierWrite);
        $this->writer()->upsert($this->definition(), [$this->layout('layout-b')], $laterWrite);

        $memo = $this->memoOf($context);
        static::assertTrue($memo->ownedBy($laterWrite));
        static::assertSame([null, $this->ids->get('layout-b-element')], $this->memoizedRootIds($memo));
    }

    /**
     * `LayoutGate::SKIP_VALIDATION_STATE` is a third-party-facing escape hatch with no core producer, so a plugin
     * importer that sets it still runs the layout field serializer: the memo holds the tree its encode produced.
     */
    #[TestDox('memoizes the tree a write encodes even when the write carries the skip state')]
    public function testAWriteUnderTheSkipStateStillMemoizesTheTreeItEncodes(): void
    {
        $this->captureEncodedLayouts();
        $context = Context::createDefaultContext();
        $context->addState(LayoutGate::SKIP_VALIDATION_STATE);
        $write = WriteContext::createFromContext($context);

        $this->writer()->upsert($this->definition(), [$this->layout('layout')], $write);

        $memo = $this->memoOf($context);
        static::assertTrue($memo->ownedBy($write));
        static::assertSame([$this->ids->get('layout-element')], $this->memoizedRootIds($memo));
    }

    /**
     * A write that dies before the validation event never reaches the validator, so its memoized trees stay on the
     * caller's `Context`. The next write on the same `Context` opens its own memo instead of adding to the
     * leftovers, and is judged on the tree behind the value it stores.
     */
    #[TestDox('judges a write against its own tree even when an earlier write on the same context died before the gate')]
    public function testWriteAfterAFailedWriteIsJudgedAgainstItsOwnTree(): void
    {
        $context = Context::createDefaultContext();
        $failedWrite = WriteContext::createFromContext($context);

        $resolvable = $this->layout('layout');

        $aborted = false;

        try {
            // Row two's layout is not a list, so its encode throws once row one's encode has memoized a tree.
            $this->writer()->insert($this->definition(), [$resolvable, ['id' => $this->ids->get('broken'), 'name' => 'memo-test-broken', 'version' => '1.0.0', 'rootSource' => 'none', 'layout' => 'not-a-tree']], $failedWrite);
        } catch (\Throwable) {
            // The abort is the fixture; which exception carries it is the serializer's business, not this test's.
            $aborted = true;
        }

        static::assertTrue($aborted, 'Expected the malformed layout payload to abort the write before the gate.');
        static::assertTrue($this->memoOf($context)->ownedBy($failedWrite), 'The failed write is expected to leave its memo behind; that is the condition under test.');

        $unresolvable = $resolvable;
        $unresolvable['layout'] = [[
            'id' => $this->ids->get('layout-unresolvable-element'),
            'component' => TestElementTypeLoader::UNRESOLVABLE,
            'properties' => [],
        ]];

        try {
            $this->repository()->upsert([$unresolvable], $context);
            static::fail('The second write carries an unresolvable tree and must be rejected on its own merits.');
        } catch (WriteException) {
        }

        static::assertNull(
            $this->repository()->searchIds(new Criteria([$this->ids->get('layout')]), $context)->firstId(),
            'The rejected write must not have stored the layout.'
        );
    }

    /**
     * The DAL retries a write's transaction on a deadlock and dispatches the validation event again without
     * encoding the layout again, so the gate of the retry must find the same tree the first attempt judged.
     * Retries run only outside an open transaction, so this case leaves the test's transaction.
     */
    #[TestDox('stores a valid layout whose write the database retried, gating it again on the same tree')]
    public function testARetriedLayoutWriteIsGatedAgainOnTheSameTree(): void
    {
        $this->failTheFirstLayoutValidationWithADeadlock();
        $context = Context::createDefaultContext();

        $this->outsideTheTestTransaction(function () use ($context): void {
            $this->repository()->create([$this->layout('layout')], $context);

            static::assertSame(2, $this->layoutValidationAttempts);
            static::assertSame(
                $this->ids->get('layout'),
                $this->repository()->searchIds(new Criteria([$this->ids->get('layout')]), $context)->firstId()
            );
        });
    }

    #[TestDox('rejects a gate-rejected layout whose write the database retried with the gate violation')]
    public function testARetriedLayoutWriteIsRejectedByTheGateAgain(): void
    {
        $this->failTheFirstLayoutValidationWithADeadlock();
        $context = Context::createDefaultContext();

        $unresolvable = $this->layout('layout');
        $unresolvable['layout'] = [[
            'id' => $this->ids->get('layout-element'),
            'component' => TestElementTypeLoader::UNRESOLVABLE,
            'properties' => [],
        ]];

        $this->outsideTheTestTransaction(function () use ($context, $unresolvable): void {
            try {
                $this->repository()->create([$unresolvable], $context);
                static::fail('Expected the retried write to be rejected by the gate.');
            } catch (WriteException $exception) {
                static::assertStringContainsString('Required property "target" is not deterministically resolvable', $exception->getMessage());
            }

            static::assertSame(2, $this->layoutValidationAttempts);
            static::assertNull($this->repository()->searchIds(new Criteria([$this->ids->get('layout')]), $context)->firstId());
        });
    }

    /**
     * Records the layout value every layout command of a write carries, which is the key the serializer memoized
     * the tree under.
     */
    private function captureEncodedLayouts(): void
    {
        $this->hooks()->on(PreWriteValidationEvent::class, function (PreWriteValidationEvent $event): void {
            foreach ($event->getCommandsForEntity(ContentLayoutDefinition::ENTITY_NAME) as $command) {
                $this->encodedLayouts[] = $command->getPayload()[ContentLayoutDefinition::LAYOUT_FIELD] ?? null;
            }
        });
    }

    /**
     * Throws a retryable deadlock after every validation listener of the first layout write attempt ran, the
     * write gate included: hooks of {@see EventHookDispatcher} run after the listeners.
     */
    private function failTheFirstLayoutValidationWithADeadlock(): void
    {
        $this->hooks()->on(PreWriteValidationEvent::class, function (PreWriteValidationEvent $event): void {
            if ($event->getCommandsForEntity(ContentLayoutDefinition::ENTITY_NAME) === []) {
                return;
            }

            ++$this->layoutValidationAttempts;

            if ($this->layoutValidationAttempts === 1) {
                throw new DeadlockException(new PdoException('Deadlock found when trying to get lock', '40001', 1213), null);
            }
        });
    }

    /**
     * Rolls back the transaction the test behaviour opened, runs the case, then deletes the layout rows the case
     * may have committed and opens a transaction again for the behaviour's teardown.
     */
    private function outsideTheTestTransaction(\Closure $case): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->rollBack();

        try {
            $case();
        } finally {
            try {
                $connection->executeStatement(
                    'DELETE FROM `content_layout` WHERE `id` IN (:ids)',
                    ['ids' => Uuid::fromHexToBytesList(array_values($this->ids->all()))],
                    ['ids' => ArrayParameterType::BINARY]
                );
            } finally {
                $connection->beginTransaction();
            }
        }
    }

    /**
     * The first root id of the tree memoized under each captured layout value, `null` where the memo holds none.
     *
     * @return list<string|null>
     */
    private function memoizedRootIds(LayoutWriteContext $memo): array
    {
        return array_map(
            static function (mixed $encoded) use ($memo): ?string {
                static::assertIsString($encoded);

                return $memo->treeFor($encoded)?->roots[0]->id;
            },
            $this->encodedLayouts
        );
    }

    private function memoOf(Context $context): LayoutWriteContext
    {
        $memo = $context->getExtension(LayoutWriteContext::EXTENSION_NAME);
        static::assertInstanceOf(LayoutWriteContext::class, $memo);

        return $memo;
    }

    /**
     * @return array<string, mixed>
     */
    private function layout(string $key): array
    {
        // content_layout carries a UNIQUE KEY on (name, version), so a batch of rows needs distinct names.
        return [
            'id' => $this->ids->get($key),
            'name' => 'memo-test-' . $key,
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [[
                'id' => $this->ids->get($key . '-element'),
                'component' => TestElementTypeLoader::RESOLVABLE,
                'properties' => [],
            ]],
        ];
    }

    private function hooks(): EventHookDispatcher
    {
        return EventHookDispatcher::fromContainer($this->getContainer());
    }

    private function definition(): ContentLayoutDefinition
    {
        $definition = $this->getContainer()->get(ContentLayoutDefinition::class);
        static::assertInstanceOf(ContentLayoutDefinition::class, $definition);

        return $definition;
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        $repository = $this->getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    private function writer(): EntityWriter
    {
        $writer = $this->getContainer()->get(EntityWriter::class);
        static::assertInstanceOf(EntityWriter::class, $writer);

        return $writer;
    }
}
