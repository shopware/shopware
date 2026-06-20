<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;

/**
 * @internal
 */
#[Package('framework')]
class ContentLayoutAssignmentWriteValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('persists a category assignment when the bound layout is resolvable for the source')]
    public function testAcceptsResolvableLayoutBoundToCategory(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->createCategory($context);
        $layoutId = $this->createLayout(TestElementTypeLoader::RESOLVABLE, $context);

        $assignmentId = Uuid::randomHex();
        $this->assignmentRepository()->create([$this->assignment($assignmentId, $categoryId, $layoutId)], $context);

        static::assertSame($assignmentId, $this->assignmentRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('rejects a category assignment when the bound layout is not resolvable for the source')]
    public function testRejectsUnresolvableLayoutBoundToCategory(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->createCategory($context);
        $layoutId = $this->createLayout(TestElementTypeLoader::UNRESOLVABLE, $context);

        $assignmentId = Uuid::randomHex();

        try {
            $this->assignmentRepository()->create([$this->assignment($assignmentId, $categoryId, $layoutId)], $context);
            static::fail('Expected the binding checker to reject the assignment of an unresolvable layout.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Required property "target" is not deterministically resolvable', $exception->getMessage());
        }

        static::assertNull($this->assignmentRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('bypasses the binding checker when the write context carries the skip flag')]
    public function testSkipFlagBypassesBindingGate(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->createCategory($context);
        $layoutId = $this->createLayout(TestElementTypeLoader::UNRESOLVABLE, $context);

        $skipContext = Context::createDefaultContext();
        $skipContext->addState(LayoutGate::SKIP_VALIDATION_STATE);
        $assignmentId = Uuid::randomHex();

        $this->assignmentRepository()->create([$this->assignment($assignmentId, $categoryId, $layoutId)], $skipContext);

        static::assertSame($assignmentId, $this->assignmentRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('rejects a single batch that creates a layout and binds an unresolvable source to it at once')]
    public function testRejectsAtomicCreateAndBindOfUnresolvableLayout(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->createCategory($context);
        $layoutId = Uuid::randomHex();
        $assignmentId = Uuid::randomHex();

        try {
            $this->layoutRepository()->create([$this->layoutWithCategoryBinding($layoutId, $assignmentId, $categoryId, TestElementTypeLoader::UNRESOLVABLE)], $context);
            static::fail('Expected the binding checker to reject the atomic create-and-bind of an unresolvable layout.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Required property "target" is not deterministically resolvable', $exception->getMessage());
            // The binding checker reports the unresolvable layout once; the well-formedness gate must not re-report it
            // for the same in-batch layout command.
            static::assertCount(1, iterator_to_array($exception->getErrors(), false));
        }

        static::assertNull($this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
        static::assertNull($this->assignmentRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('persists a single batch that creates a layout and binds a resolvable source to it at once')]
    public function testAcceptsAtomicCreateAndBindOfResolvableLayout(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->createCategory($context);
        $layoutId = Uuid::randomHex();
        $assignmentId = Uuid::randomHex();

        $this->layoutRepository()->create([$this->layoutWithCategoryBinding($layoutId, $assignmentId, $categoryId, TestElementTypeLoader::RESOLVABLE)], $context);

        static::assertSame($layoutId, $this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
        static::assertSame($assignmentId, $this->assignmentRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('rejects an atomic create-and-bind of an unresolvable layout via the Sync API')]
    public function testRejectsAtomicCreateAndBindViaSyncApi(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->createCategory($context);
        $layoutId = Uuid::randomHex();
        $assignmentId = Uuid::randomHex();

        $operations = [
            new SyncOperation(
                'create-and-bind',
                ContentLayoutDefinition::ENTITY_NAME,
                SyncOperation::ACTION_UPSERT,
                [$this->layoutWithCategoryBinding($layoutId, $assignmentId, $categoryId, TestElementTypeLoader::UNRESOLVABLE)],
            ),
        ];

        try {
            $this->syncService()->sync($operations, $context, new SyncBehavior());
            static::fail('Expected the binding checker to reject the atomic Sync create-and-bind of an unresolvable layout.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Required property "target" is not deterministically resolvable', $exception->getMessage());
        }

        static::assertNull($this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
        static::assertNull($this->assignmentRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    private function createCategory(Context $context): string
    {
        $id = Uuid::randomHex();
        $repository = $this->getContainer()->get('category.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);
        $repository->create([['id' => $id, 'name' => 'binding-gate-category']], $context);

        return $id;
    }

    private function createLayout(string $component, Context $context): string
    {
        $id = Uuid::randomHex();
        $this->layoutRepository()->create([[
            'id' => $id,
            'name' => 'binding-gate-layout',
            'version' => '1.0.0',
            'layout' => [
                ['id' => Uuid::randomHex(), 'component' => $component, 'properties' => []],
            ],
        ]], $context);

        return $id;
    }

    /**
     * @return array<string, string>
     */
    private function assignment(string $id, string $categoryId, string $layoutId): array
    {
        return ['id' => $id, 'categoryId' => $categoryId, 'contentLayoutId' => $layoutId];
    }

    /**
     * A layout payload that nests its category assignment, so the layout INSERT and the assignment INSERT land in
     * a single write batch (one PreWriteValidationEvent) — the atomic create-and-bind path.
     *
     * @return array<string, mixed>
     */
    private function layoutWithCategoryBinding(string $layoutId, string $assignmentId, string $categoryId, string $component): array
    {
        return [
            'id' => $layoutId,
            'name' => 'atomic-create-and-bind-layout',
            'version' => '1.0.0',
            'layout' => [
                ['id' => Uuid::randomHex(), 'component' => $component, 'properties' => []],
            ],
            'categoryContentLayouts' => [
                ['id' => $assignmentId, 'categoryId' => $categoryId],
            ],
        ];
    }

    private function syncService(): SyncService
    {
        $service = $this->getContainer()->get(SyncService::class);
        static::assertInstanceOf(SyncService::class, $service);

        return $service;
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function layoutRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function assignmentRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('category_content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
