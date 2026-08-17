<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteContext;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * The memo the layout field serializer leaves on the write `Context` is consumed by the write validator, so a
 * completed write leaves nothing on a caller-owned `Context`. These cases drive the real write paths, because
 * the memo's keying depends on how the DAL orders normalize against extract and on which `Context` instance
 * each stage sees — neither of which a unit test exercises.
 *
 * @internal
 */
#[Package('framework')]
class ContentLayoutWriteMemoLifetimeTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('leaves no memoized tree on the context after a repository write')]
    public function testRepositoryWriteEmptiesTheMemo(): void
    {
        $context = Context::createDefaultContext();

        $this->repository()->create([$this->layout('layout')], $context);

        static::assertTrue($this->memoOf($context)->isEmpty());
    }

    #[TestDox('leaves no memoized tree on the context after a sync write carrying several operations')]
    public function testSyncWriteWithSeveralOperationsEmptiesTheMemo(): void
    {
        $context = Context::createDefaultContext();

        // The sync path is what makes the entity-plus-primary-key keying load-bearing: normalize sees a bare
        // row index as its write path while the command the validator reads carries the operation key too.
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
            WriteContext::createFromContext($context)
        );

        static::assertCount(3, $this->repository()->searchIds(
            new Criteria([$this->ids->get('layout-a'), $this->ids->get('layout-b'), $this->ids->get('layout-c')]),
            $context
        )->getIds());
        static::assertTrue($this->memoOf($context)->isEmpty());
    }

    /**
     * The DAL keeps a command per written row rather than collapsing rows that share a primary key, so one
     * batch carrying the same layout id twice reaches the gate twice and needs both of its memoized trees.
     */
    #[TestDox('leaves no memoized tree on the context after one batch writes the same layout id twice')]
    public function testBatchWritingOneLayoutIdTwiceEmptiesTheMemo(): void
    {
        $context = Context::createDefaultContext();

        $edit = [
            'id' => $this->ids->get('layout'),
            'layout' => [[
                'id' => $this->ids->get('layout-second-element'),
                'component' => TestElementTypeLoader::RESOLVABLE,
                'properties' => [],
            ]],
        ];

        $this->repository()->upsert([$this->layout('layout'), $edit], $context);

        // The stored tree proves the second row really wrote the layout column, so the gate did see two
        // layout-touching commands for this id and both of their memoized trees were drained.
        $stored = $this->repository()->search(new Criteria([$this->ids->get('layout')]), $context)->getEntities()->first();
        static::assertNotNull($stored);
        static::assertSame([$this->ids->get('layout-second-element')], array_map(
            static fn (StoredElement $element): string => $element->id,
            $stored->getLayout()
        ));
        static::assertTrue($this->memoOf($context)->isEmpty());
    }

    /**
     * `LayoutGate::SKIP_VALIDATION_STATE` is a third-party-facing escape hatch with no core producer — every
     * site that adds the state today is a test, and nothing anywhere removes it — so this case is not dead:
     * a memo leaking on that path would be a real defect for a plugin whose long-lived importer sets the state
     * and reuses its `Context`.
     */
    #[TestDox('leaves no memoized tree on the context after a write carrying the skip state')]
    public function testSkipStateWriteEmptiesTheMemo(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(LayoutGate::SKIP_VALIDATION_STATE);

        $this->repository()->create([$this->layout('layout')], $context);

        static::assertTrue($this->memoOf($context)->isEmpty());
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
