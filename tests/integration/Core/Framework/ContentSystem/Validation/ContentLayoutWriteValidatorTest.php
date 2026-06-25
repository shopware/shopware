<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
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

    #[TestDox('persists a layout that is resolvable for its declared root source')]
    public function testAcceptsResolvableLayout(): void
    {
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

        $this->repository()->create([$this->layout('category', TestElementTypeLoader::RESOLVABLE, $id)], $context);

        static::assertSame($id, $this->repository()->searchIds(new Criteria([$id]), $context)->firstId());
    }

    #[TestDox('rejects a layout that is not resolvable for its declared root source on creation')]
    public function testRejectsUnresolvableLayoutOnCreation(): void
    {
        $context = Context::createDefaultContext();

        try {
            $this->repository()->create([$this->layout('category', TestElementTypeLoader::UNRESOLVABLE)], $context);
            static::fail('Expected the resolvability gate to reject the unresolvable layout on creation.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Required property "target" is not deterministically resolvable', $exception->getMessage());
        }
    }

    #[TestDox('rejects a none-rooted layout that is not resolvable')]
    public function testRejectsNoneRootedUnresolvableLayout(): void
    {
        // `none` is the special-cased root source that exposes no root context. This guards that the write gate still
        // runs resolvability for it (rather than letting an empty root context pass) — a branch the category-rooted
        // reject test does not exercise, since that one resolves a non-empty entity context.
        $context = Context::createDefaultContext();

        try {
            $this->repository()->create([$this->layout('none', TestElementTypeLoader::UNRESOLVABLE)], $context);
            static::fail('Expected the resolvability gate to reject the unresolvable none-rooted layout.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Required property "target" is not deterministically resolvable', $exception->getMessage());
        }
    }

    #[TestDox('accepts a none-rooted layout that needs no root context')]
    public function testAcceptsNoneRootedResolvableLayout(): void
    {
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

        $this->repository()->create([$this->layout('none', TestElementTypeLoader::RESOLVABLE, $id)], $context);

        static::assertSame($id, $this->repository()->searchIds(new Criteria([$id]), $context)->firstId());
    }

    #[TestDox('rejects an unregistered root source on creation with a membership violation')]
    public function testRejectsUnknownRootSource(): void
    {
        $context = Context::createDefaultContext();

        try {
            $this->repository()->create([$this->layout('definitely-not-a-root-source', TestElementTypeLoader::RESOLVABLE)], $context);
            static::fail('Expected the membership check to reject the unregistered root source.');
        } catch (WriteException $exception) {
            static::assertSame(ContentSystemException::UNKNOWN_ROOT_SOURCE, iterator_to_array($exception->getErrors(), false)[0]['code']);
        }
    }

    #[TestDox('rejects a layout with an unregistered component on write')]
    public function testRejectsUnregisteredComponent(): void
    {
        $context = Context::createDefaultContext();

        try {
            $this->repository()->create([$this->layout('category', 'Sw:Test:DefinitelyUnregistered')], $context);
            static::fail('Expected the well-formedness gate to reject the unregistered component.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Sw:Test:DefinitelyUnregistered', $exception->getMessage());
            static::assertStringContainsString('is not a registered element type', $exception->getMessage());
        }
    }

    #[TestDox('bypasses every check when the write context carries the skip flag')]
    public function testSkipFlagBypassesGate(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(LayoutGate::SKIP_VALIDATION_STATE);
        $id = Uuid::randomHex();

        $this->repository()->create([$this->layout('category', 'Sw:Test:DefinitelyUnregistered', $id)], $context);

        static::assertSame($id, $this->repository()->searchIds(new Criteria([$id]), $context)->firstId());
    }

    #[TestDox('rejects an edit that makes the layout unresolvable for its committed root source')]
    public function testRejectsEditBreakingResolvability(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $this->repository()->create([$this->layout('category', TestElementTypeLoader::RESOLVABLE, $layoutId)], $context);

        try {
            $this->repository()->update([['id' => $layoutId, 'layout' => $this->tree(TestElementTypeLoader::UNRESOLVABLE)]], $context);
            static::fail('Expected the gate to re-validate the edit against the committed root source.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Required property "target" is not deterministically resolvable', $exception->getMessage());
        }
    }

    #[TestDox('accepts an edit that keeps the layout resolvable for its committed root source')]
    public function testAcceptsResolvableEdit(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $this->repository()->create([$this->layout('category', TestElementTypeLoader::RESOLVABLE, $layoutId)], $context);

        $this->repository()->update([['id' => $layoutId, 'name' => 'renamed-layout', 'layout' => $this->tree(TestElementTypeLoader::RESOLVABLE)]], $context);

        $layout = $this->repository()->search(new Criteria([$layoutId]), $context)->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);
        static::assertSame('renamed-layout', $layout->getName());
    }

    #[TestDox('rejects an update that changes the immutable root source and leaves the stored value unchanged')]
    public function testRejectsRootSourceChange(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $this->repository()->create([$this->layout('category', TestElementTypeLoader::RESOLVABLE, $layoutId)], $context);

        try {
            $this->repository()->update([['id' => $layoutId, 'rootSource' => 'product']], $context);
            static::fail('Expected the DAL to reject the change of the immutable root source.');
        } catch (WriteException $exception) {
            // Pin the rejection to the root_source field: the EntityWriteGateway immutable-violation message is
            // 'The field "root_source" of "content_layout" is immutable and cannot be updated.'
            static::assertStringContainsString('immutable', $exception->getMessage());
            static::assertStringContainsString('root_source', $exception->getMessage());
        }

        // The gate aborts the batch pre-commit, so the stored value must still be the original.
        $persisted = $this->repository()->search(new Criteria([$layoutId]), $context)->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $persisted);
        static::assertSame('category', $persisted->getRootSource());
    }

    /**
     * @return array<string, mixed>
     */
    private function layout(string $rootSource, string $component, ?string $id = null): array
    {
        return [
            'id' => $id ?? Uuid::randomHex(),
            'name' => 'gate-test',
            'version' => '1.0.0',
            'rootSource' => $rootSource,
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
