<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Revision;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeConstraints;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\BoxSpacingNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionConstraintDeriver;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutRowLock;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteBoundary;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutDraft;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevision;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionStore;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\RevisionGraph;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTreeStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutRevisionService::class)]
class LayoutRevisionServiceTest extends TestCase
{
    private const LAYOUT_ID = 'layout';

    private const DRAFT_ID = 'draft';

    private const HEAD = 'head';

    #[TestDox('an internal fault of the write boundary on a draft edit propagates instead of being reported as an invalid layout')]
    public function testAppendRevisionPropagatesAnInternalBoundaryFault(): void
    {
        $fault = ContentSystemException::bindingSpecificationCanonicalizationFailed('Sw:Block', 'unexpanded sugar');

        $this->expectExceptionObject($fault);

        $this->appendThroughBoundaryFailing($fault);
    }

    #[TestDox('a draft edit repeating an element id is rejected as an invalid layout structure before the write boundary rebuilds it')]
    public function testAppendRevisionRejectsADuplicateElementIdBeforeTheBoundaryPasses(): void
    {
        $reconciler = static::createStub(AttributionReconciler::class);
        $reconciler->method('reconcile')->willThrowException(ContentSystemException::invalidFieldValueType('layout', 'StoredElement', 'string'));
        $events = [];

        try {
            $this->service($reconciler, $this->observedRowLock($events), $this->store($events))->appendRevision(
                self::LAYOUT_ID,
                self::DRAFT_ID,
                self::HEAD,
                static fn (): StoredTree => new StoredTree([
                    StoredElementBuilder::create('Sw:Block', 'twin')->build(),
                    StoredElementBuilder::create('Sw:Block', 'twin')->build(),
                ]),
                Context::createDefaultContext(),
            );
            static::fail('Expected the repeated element id to be rejected.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
        }
    }

    #[TestDox('a client defect the write boundary finds on a draft edit is rejected as an invalid layout write')]
    public function testAppendRevisionRejectsABoundaryClientDefect(): void
    {
        $defect = ContentSystemException::invalidFieldValueType('layout', 'StoredElement', 'string');

        try {
            $this->appendThroughBoundaryFailing($defect);
            static::fail('Expected the client defect to be rejected as a layout write.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertSame(ContentSystemException::INVALID_FIELD_VALUE_TYPE, $exception->getViolations()->get(0)->getCode());
        }
    }

    /**
     * @param \Closure(LayoutRevisionService): mixed $operation
     */
    #[DataProvider('graphOperations')]
    #[TestDox('$_dataName reads the live state and writes the revision graph only inside the layout row lock')]
    public function testRunsInsideTheRowLock(\Closure $operation, string $write): void
    {
        $events = [];

        $operation($this->service(static::createStub(AttributionReconciler::class), $this->observedRowLock($events), $this->store($events)));

        static::assertSame('enter', array_shift($events));
        static::assertSame('publication', array_shift($events));
        static::assertSame('commit', array_pop($events));
        static::assertContains($write, $events);
        static::assertNotContains('enter', $events);
        static::assertNotContains('commit', $events);
    }

    #[TestDox('publishing invalidates the layout cache once the row lock transaction has committed')]
    public function testPublishingInvalidatesTheLayoutCacheAfterTheCommit(): void
    {
        $events = [];
        $cacheInvalidator = static::createStub(CacheInvalidator::class);
        $cacheInvalidator->method('invalidate')->willReturnCallback(static function (array $tags) use (&$events): void {
            $events[] = 'invalidate ' . implode(', ', $tags);
        });

        $this->service(static::createStub(AttributionReconciler::class), $this->observedRowLock($events), $this->store($events), $cacheInvalidator)
            ->publish(self::LAYOUT_ID, self::DRAFT_ID, self::HEAD, Context::createDefaultContext());

        static::assertSame('commit', $events[\count($events) - 2]);
        static::assertSame('invalidate ' . ContentSection::MAIN->buildLayoutTag(self::LAYOUT_ID), $events[\count($events) - 1]);
    }

    public static function graphOperations(): \Generator
    {
        yield 'creating a draft' => [
            static fn (LayoutRevisionService $service): LayoutDraft => $service->createDraft(self::LAYOUT_ID, Context::createDefaultContext()),
            'saveDraft',
        ];

        yield 'editing a draft' => [
            static fn (LayoutRevisionService $service): array => $service->appendRevision(
                self::LAYOUT_ID,
                self::DRAFT_ID,
                self::HEAD,
                static fn (): StoredTree => new StoredTree([StoredElementBuilder::create('Sw:Block', 'el')->build()]),
                Context::createDefaultContext(),
            ),
            'insertRevision',
        ];

        yield 'publishing a draft' => [
            static fn (LayoutRevisionService $service) => $service->publish(self::LAYOUT_ID, self::DRAFT_ID, self::HEAD, Context::createDefaultContext()),
            'markPublished',
        ];

        yield 'discarding a draft' => [
            static fn (LayoutRevisionService $service) => $service->discardDraft(self::LAYOUT_ID, self::DRAFT_ID),
            'deleteDraft',
        ];
    }

    private function appendThroughBoundaryFailing(ContentSystemException $failure): void
    {
        $reconciler = static::createStub(AttributionReconciler::class);
        $reconciler->method('reconcile')->willThrowException($failure);

        $rowLock = static::createStub(LayoutRowLock::class);
        $rowLock->method('run')->willReturnCallback(static fn (string $layoutId, \Closure $operation): mixed => $operation());
        $events = [];

        $this->service($reconciler, $rowLock, $this->store($events))->appendRevision(
            self::LAYOUT_ID,
            self::DRAFT_ID,
            self::HEAD,
            static fn (): StoredTree => new StoredTree([StoredElementBuilder::create('Sw:Block', 'el')->build()]),
            Context::createDefaultContext(),
        );
    }

    /**
     * @param list<string> $events
     */
    private function observedRowLock(array &$events): LayoutRowLock
    {
        $rowLock = static::createStub(LayoutRowLock::class);
        $rowLock->method('run')->willReturnCallback(static function (string $layoutId, \Closure $operation) use (&$events): mixed {
            $events[] = 'enter';
            $result = $operation();
            $events[] = 'commit';

            return $result;
        });

        return $rowLock;
    }

    /**
     * Records the read of the live state and every write to the revision graph.
     *
     * @param list<string> $events
     */
    private function store(array &$events): LayoutRevisionStore
    {
        $now = new \DateTimeImmutable();
        $record = static function (string $event) use (&$events): void {
            $events[] = $event;
        };

        $store = static::createStub(LayoutRevisionStore::class);
        $store->method('publication')->willReturnCallback(static function () use ($record, $now): array {
            $record('publication');

            return ['revisionId' => self::HEAD, 'current' => true, 'writtenAt' => $now, 'checksum' => ''];
        });
        $store->method('draft')->willReturn(new LayoutDraft(self::DRAFT_ID, 'Draft', self::HEAD, self::HEAD, self::HEAD, $now, null, $now));
        $store->method('tree')->willReturn([]);
        $store->method('graph')->willReturn(new RevisionGraph([self::HEAD => new LayoutRevision(self::HEAD, null, $now, null)], [], self::HEAD));

        foreach (['saveDraft', 'insertRevision', 'deleteDraft', 'adoptLayoutColumn', 'markPublished', 'reparent', 'deleteRevisions'] as $write) {
            $store->method($write)->willReturnCallback(static fn () => $record($write));
        }

        return $store;
    }

    private function service(AttributionReconciler $reconciler, LayoutRowLock $rowLock, LayoutRevisionStore $store, ?CacheInvalidator $cacheInvalidator = null): LayoutRevisionService
    {
        $seeder = static::createStub(LayoutDefaultSeeder::class);
        $seeder->method('seed')->willReturnArgument(0);

        $styleOptions = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $styleOptions->method('all')->willReturn([]);

        $configProvider = static::createStub(DataLoaderConfigSerializerProvider::class);

        $gate = static::createStub(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));

        return new LayoutRevisionService(
            $rowLock,
            $store,
            new LayoutWriteBoundary(
                $seeder,
                new StoredTreeStyleNormalizer(new ElementStyleNormalizer($styleOptions, new BoxSpacingNormalizer())),
                $reconciler,
                new ViolationConstraintMapper(),
            ),
            new StoredTreeCodec(new StoredElementCodec($configProvider)),
            new StoredTreeConstraints($styleOptions, new StyleOptionConstraintDeriver()),
            static::createStub(ValidatorInterface::class),
            $gate,
            new ViolationConstraintMapper(),
            StaticEntityRepository::of(ContentLayoutCollection::class, []),
            new MockClock(),
            $cacheInvalidator ?? static::createStub(CacheInvalidator::class),
        );
    }
}
