<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutResolvabilityValidator;
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
            static::fail('Expected the binding gate to reject the assignment of an unresolvable layout.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Required property "target" is not deterministically resolvable', $exception->getMessage());
        }

        static::assertNull($this->assignmentRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
    }

    #[TestDox('bypasses the binding gate when the write context carries the skip flag')]
    public function testSkipFlagBypassesBindingGate(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->createCategory($context);
        $layoutId = $this->createLayout(TestElementTypeLoader::UNRESOLVABLE, $context);

        $skipContext = Context::createDefaultContext();
        $skipContext->addState(LayoutResolvabilityValidator::SKIP_VALIDATION_STATE);
        $assignmentId = Uuid::randomHex();

        $this->assignmentRepository()->create([$this->assignment($assignmentId, $categoryId, $layoutId)], $skipContext);

        static::assertSame($assignmentId, $this->assignmentRepository()->searchIds(new Criteria([$assignmentId]), $context)->firstId());
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
