<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Mutation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutDraft;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\PersistedLayoutMutator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;

/**
 * @internal
 */
#[Package('framework')]
class PersistedDraftMutationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private PersistedLayoutMutator $mutator;

    private LayoutRevisionService $revisions;

    private Context $context;

    protected function setUp(): void
    {
        $this->mutator = static::getContainer()->get(PersistedLayoutMutator::class);
        $this->revisions = static::getContainer()->get(LayoutRevisionService::class);
        $this->context = Context::createDefaultContext();
    }

    #[TestDox('a mutation on a draft appends one revision on the head and leaves the live layout alone')]
    public function testMutationAppendsOneRevisionOnTheDraft(): void
    {
        $layoutId = $this->createLayout(['a', 'b']);
        $draft = $this->revisions->createDraft($layoutId, $this->context);
        $revisionsBefore = $this->revisionRows($layoutId);

        $result = $this->mutator->mutateDraft($layoutId, $draft->id, $draft->head, new RemoveElement('a'), $this->context);

        static::assertSame($draft->head, $result->revision->parent);
        static::assertSame($result->revision->id, $result->draft->head);
        static::assertSame(['b'], $this->ids($result->mutation->layout->roots));
        static::assertSame(['b'], $this->ids($this->revisions->getDraft($layoutId, $draft->id)['layout']));
        static::assertSame($revisionsBefore + 1, $this->revisionRows($layoutId));
        static::assertSame(['a', 'b'], $this->liveIds($layoutId));
    }

    #[TestDox('consecutive mutations chain their revisions through the head each one returns')]
    public function testConsecutiveMutationsChainOnTheReturnedHead(): void
    {
        $layoutId = $this->createLayout(['a', 'b', 'c']);
        $draft = $this->revisions->createDraft($layoutId, $this->context);

        $first = $this->mutator->mutateDraft($layoutId, $draft->id, $draft->head, new RemoveElement('a'), $this->context);
        $second = $this->mutator->mutateDraft($layoutId, $draft->id, $first->draft->head, new RemoveElement('b'), $this->context);

        static::assertSame($first->revision->id, $second->revision->parent);
        static::assertSame(['c'], $this->ids($second->mutation->layout->roots));
    }

    #[TestDox('a stale head is a version conflict and appends nothing')]
    public function testStaleHeadConflicts(): void
    {
        $layoutId = $this->createLayout(['a', 'b']);
        $draft = $this->revisions->createDraft($layoutId, $this->context);
        $this->mutator->mutateDraft($layoutId, $draft->id, $draft->head, new RemoveElement('a'), $this->context);

        $this->assertFailsWithoutAppending(
            $layoutId,
            ContentSystemException::LAYOUT_VERSION_CONFLICT,
            fn () => $this->mutator->mutateDraft($layoutId, $draft->id, $draft->head, new RemoveElement('b'), $this->context),
        );
    }

    #[TestDox('an operation that rejects the edit appends nothing and leaves the head where it was')]
    public function testRejectedOperationAppendsNothing(): void
    {
        $layoutId = $this->createLayout(['a']);
        $draft = $this->revisions->createDraft($layoutId, $this->context);

        $this->assertFailsWithoutAppending(
            $layoutId,
            ContentSystemException::MUTATION_TARGET_NOT_FOUND,
            fn () => $this->mutator->mutateDraft($layoutId, $draft->id, $draft->head, new RemoveElement('unknown'), $this->context),
        );

        static::assertSame($draft->head, $this->draft($layoutId, $draft->id)->head);
    }

    #[TestDox('an unknown draft is not found')]
    public function testUnknownDraftIsNotFound(): void
    {
        $layoutId = $this->createLayout(['a']);
        $draftId = Uuid::randomHex();

        $this->expectExceptionObject(ContentSystemException::contentLayoutDraftNotFound($layoutId, $draftId));

        $this->mutator->mutateDraft($layoutId, $draftId, Uuid::randomHex(), new RemoveElement('a'), $this->context);
    }

    #[TestDox('a declared write privilege the caller lacks is a 403 before the draft is touched')]
    public function testDeclaredWritePrivilegeIsRequired(): void
    {
        $layoutId = $this->createLayout(['a']);
        $draft = $this->revisions->createDraft($layoutId, $this->context);
        $mutation = $this->createMock(LayoutMutation::class);
        $mutation->method('writePrivilege')->willReturn('content_layout:translate');
        $mutation->expects($this->never())->method('apply');
        $source = new AdminApiSource(null);
        $source->setPermissions(['content_layout:read', 'content_layout:update']);

        $revisionsBefore = $this->revisionRows($layoutId);

        try {
            $this->mutator->mutateDraft($layoutId, $draft->id, $draft->head, $mutation, Context::createDefaultContext($source));
            static::fail('Expected the missing translate privilege to be rejected.');
        } catch (MissingPrivilegeException $exception) {
            static::assertSame(ContentSystemException::missingPrivileges(['content_layout:translate'])->getMessage(), $exception->getMessage());
        }

        static::assertSame($revisionsBefore, $this->revisionRows($layoutId));
    }

    /**
     * @param \Closure(): mixed $operation
     */
    private function assertFailsWithoutAppending(string $layoutId, string $errorCode, \Closure $operation): void
    {
        $revisionsBefore = $this->revisionRows($layoutId);

        try {
            $operation();
            static::fail('Expected a ' . $errorCode . ' failure.');
        } catch (ContentSystemException $exception) {
            static::assertSame($errorCode, $exception->getErrorCode());
        }

        static::assertSame($revisionsBefore, $this->revisionRows($layoutId));
    }

    private function revisionRows(string $layoutId): int
    {
        return (int) static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM `content_layout_revision` WHERE `content_layout_id` = :id',
            ['id' => Uuid::fromHexToBytes($layoutId)],
        );
    }

    private function draft(string $layoutId, string $draftId): LayoutDraft
    {
        return $this->revisions->getDraft($layoutId, $draftId)['draft'];
    }

    /**
     * @param list<string> $elementIds
     */
    private function createLayout(array $elementIds): string
    {
        $id = Uuid::randomHex();

        $this->repository()->create([[
            'id' => $id,
            'name' => 'draft-mutation-' . $id,
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => array_map(
                static fn (string $elementId): array => ['id' => $elementId, 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => []],
                $elementIds,
            ),
        ]], $this->context);

        return $id;
    }

    /**
     * @param list<StoredElement> $elements
     *
     * @return list<string>
     */
    private function ids(array $elements): array
    {
        return array_map(static fn (StoredElement $element): string => $element->id, $elements);
    }

    /**
     * @return list<string>
     */
    private function liveIds(string $layoutId): array
    {
        $layout = $this->repository()->search(new Criteria([$layoutId]), $this->context)->getEntities()->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);

        return $this->ids($layout->getLayout());
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        return static::getContainer()->get('content_layout.repository');
    }
}
