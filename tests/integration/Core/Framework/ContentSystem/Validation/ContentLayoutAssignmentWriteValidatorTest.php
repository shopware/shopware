<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
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
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class ContentLayoutAssignmentWriteValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('persists a category assignment when the layout root source matches the assignment type')]
    public function testAcceptsAssignmentMatchingRootSource(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->createCategory($context);
        $layoutId = $this->createLayout('category', $context);

        $assignmentId = $this->ids->get('assignment');
        $this->assignmentRepository()->create([$this->assignment($assignmentId, $categoryId, $layoutId)], $context);

        static::assertSame($assignmentId, $this->assignmentRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('rejects a category assignment when the layout was created for a different root source')]
    public function testRejectsAssignmentMismatchingRootSource(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->createCategory($context);
        $layoutId = $this->createLayout('product', $context);

        $assignmentId = $this->ids->get('assignment');

        try {
            $this->assignmentRepository()->create([$this->assignment($assignmentId, $categoryId, $layoutId)], $context);
            static::fail('Expected the type-match to reject the assignment of a product-rooted layout to a category.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('root source is "product"', $exception->getMessage());
            static::assertSame(ContentSystemException::ROOT_SOURCE_ASSIGNMENT_MISMATCH, iterator_to_array($exception->getErrors(), false)[0]['code']);
        }

        static::assertNull($this->assignmentRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('bypasses the type-match when the write context carries the skip flag')]
    public function testSkipFlagBypassesTypeMatch(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->createCategory($context);
        $layoutId = $this->createLayout('product', $context);

        $skipContext = Context::createDefaultContext();
        $skipContext->addState(LayoutGate::SKIP_VALIDATION_STATE);
        $assignmentId = $this->ids->get('assignment');

        $this->assignmentRepository()->create([$this->assignment($assignmentId, $categoryId, $layoutId)], $skipContext);

        static::assertSame($assignmentId, $this->assignmentRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('persists a single batch that creates a category-rooted layout and binds a category at once')]
    public function testAcceptsAtomicCreateAndBindMatchingRootSource(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->createCategory($context);
        $layoutId = $this->ids->get('layout');
        $assignmentId = $this->ids->get('assignment');

        $this->layoutRepository()->create([$this->layoutWithCategoryBinding($layoutId, $assignmentId, $categoryId, 'category')], $context);

        static::assertSame($layoutId, $this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
        static::assertSame($assignmentId, $this->assignmentRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('rejects an atomic create-and-bind whose in-flight root source does not match the assignment type')]
    public function testRejectsAtomicCreateAndBindMismatchingRootSource(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->createCategory($context);
        $layoutId = $this->ids->get('layout');
        $assignmentId = $this->ids->get('assignment');

        try {
            $this->layoutRepository()->create([$this->layoutWithCategoryBinding($layoutId, $assignmentId, $categoryId, 'product')], $context);
            static::fail('Expected the type-match to read the in-flight root source and reject the create-and-bind.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('root source is "product"', $exception->getMessage());
        }

        static::assertNull($this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
        static::assertNull($this->assignmentRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('rejects a mismatching atomic create-and-bind via the Sync API')]
    public function testRejectsAtomicCreateAndBindMismatchViaSyncApi(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->createCategory($context);
        $layoutId = $this->ids->get('layout');
        $assignmentId = $this->ids->get('assignment');

        $operations = [
            new SyncOperation(
                'create-and-bind',
                ContentLayoutDefinition::ENTITY_NAME,
                SyncOperation::ACTION_UPSERT,
                [$this->layoutWithCategoryBinding($layoutId, $assignmentId, $categoryId, 'product')],
            ),
        ];

        try {
            $this->syncService()->sync($operations, $context, new SyncBehavior());
            static::fail('Expected the type-match to reject the atomic Sync create-and-bind of a product-rooted layout to a category.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('root source is "product"', $exception->getMessage());
        }

        static::assertNull($this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
        static::assertNull($this->assignmentRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    private function createCategory(Context $context): string
    {
        $id = $this->ids->get('category');
        $repository = $this->getContainer()->get('category.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);
        $repository->create([['id' => $id, 'name' => 'binding-gate-category']], $context);

        return $id;
    }

    private function createLayout(string $rootSource, Context $context): string
    {
        $id = $this->ids->get('layout');
        $this->layoutRepository()->create([[
            'id' => $id,
            'name' => 'binding-gate-layout',
            'version' => '1.0.0',
            'rootSource' => $rootSource,
            'layout' => [
                ['id' => $this->ids->get('element'), 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => []],
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
     * a single write batch (one PreWriteValidationEvent) — the atomic create-and-bind path. The assignment
     * type-match reads the layout's in-flight root source rather than the (not-yet-committed) row.
     *
     * @return array<string, mixed>
     */
    private function layoutWithCategoryBinding(string $layoutId, string $assignmentId, string $categoryId, string $rootSource): array
    {
        return [
            'id' => $layoutId,
            'name' => 'atomic-create-and-bind-layout',
            'version' => '1.0.0',
            'rootSource' => $rootSource,
            'layout' => [
                ['id' => $this->ids->get('element'), 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => []],
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
