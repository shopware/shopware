<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\Context;
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
class ContentLayoutWriteValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('persists an incomplete but well-formed layout')]
    public function testAcceptsIncompleteButWellFormedLayout(): void
    {
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

        $this->repository()->create([$this->layout(TestElementTypeLoader::RESOLVABLE, $id)], $context);

        static::assertSame($id, $this->repository()->searchIds(new Criteria([$id]), $context)->firstId());
    }

    #[TestDox('rejects a content layout with an unregistered component on write')]
    public function testRejectsUnregisteredComponent(): void
    {
        $context = Context::createDefaultContext();

        try {
            $this->repository()->create([$this->layout('Sw:Test:DefinitelyUnregistered')], $context);
            static::fail('Expected the well-formedness gate to reject the unregistered component.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Sw:Test:DefinitelyUnregistered', $exception->getMessage());
            static::assertStringContainsString('is not a registered element type', $exception->getMessage());
        }
    }

    #[TestDox('bypasses the well-formedness gate when the write context carries the skip flag')]
    public function testSkipFlagBypassesGate(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(LayoutGate::SKIP_VALIDATION_STATE);
        $id = Uuid::randomHex();

        $this->repository()->create([$this->layout('Sw:Test:DefinitelyUnregistered', $id)], $context);

        static::assertSame($id, $this->repository()->searchIds(new Criteria([$id]), $context)->firstId());
    }

    #[TestDox('rejects an edit that makes a category-bound layout unresolvable')]
    public function testRejectsEditBreakingResolvabilityForBoundCategory(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $this->repository()->create([$this->layout(TestElementTypeLoader::RESOLVABLE, $layoutId)], $context);
        $this->bindCategory($layoutId, $context);

        try {
            $this->repository()->update([['id' => $layoutId, 'layout' => $this->tree(TestElementTypeLoader::UNRESOLVABLE)]], $context);
            static::fail('Expected the bound-source re-check to reject the breaking edit.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Required property "target" is not deterministically resolvable', $exception->getMessage());
        }
    }

    #[TestDox('accepts an edit that keeps a category-bound layout resolvable')]
    public function testAcceptsResolvableEditForBoundCategory(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $this->repository()->create([$this->layout(TestElementTypeLoader::RESOLVABLE, $layoutId)], $context);
        $this->bindCategory($layoutId, $context);

        $this->repository()->update([['id' => $layoutId, 'name' => 'renamed-bound-layout', 'layout' => $this->tree(TestElementTypeLoader::RESOLVABLE)]], $context);

        $layout = $this->repository()->search(new Criteria([$layoutId]), $context)->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);
        static::assertSame('renamed-bound-layout', $layout->getName());
    }

    private function bindCategory(string $layoutId, Context $context): void
    {
        $categoryId = Uuid::randomHex();
        $categoryRepository = $this->getContainer()->get('category.repository');
        static::assertInstanceOf(EntityRepository::class, $categoryRepository);
        $categoryRepository->create([['id' => $categoryId, 'name' => 'recheck-category']], $context);

        $assignmentRepository = $this->getContainer()->get('category_content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $assignmentRepository);
        $assignmentRepository->create([['id' => Uuid::randomHex(), 'categoryId' => $categoryId, 'contentLayoutId' => $layoutId]], $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function layout(string $component, ?string $id = null): array
    {
        return [
            'id' => $id ?? Uuid::randomHex(),
            'name' => 'gate-test',
            'version' => '1.0.0',
            'layout' => $this->tree($component),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tree(string $component): array
    {
        return [
            ['id' => Uuid::randomHex(), 'component' => $component, 'properties' => []],
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
}
