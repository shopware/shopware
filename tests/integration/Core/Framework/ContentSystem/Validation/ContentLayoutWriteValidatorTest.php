<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\Context;
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
class ContentLayoutWriteValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('persists a layout that is resolvable for its declared root source')]
    public function testAcceptsResolvableLayout(): void
    {
        $context = Context::createDefaultContext();
        $id = $this->ids->get('layout');

        $this->repository()->create([$this->layout('category', TestElementTypeLoader::RESOLVABLE, $id)], $context);

        static::assertSame($id, $this->repository()->searchIds(new Criteria([$id]), $context)->firstId());
    }

    #[TestDox('accepts a none-rooted layout that needs no root context')]
    public function testAcceptsNoneRootedResolvableLayout(): void
    {
        $context = Context::createDefaultContext();
        $id = $this->ids->get('layout');

        $this->repository()->create([$this->layout('none', TestElementTypeLoader::RESOLVABLE, $id)], $context);

        static::assertSame($id, $this->repository()->searchIds(new Criteria([$id]), $context)->firstId());
    }

    #[TestDox('accepts an edit that keeps the layout resolvable for its committed root source')]
    public function testAcceptsResolvableEdit(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->ids->get('layout');
        $this->repository()->create([$this->layout('category', TestElementTypeLoader::RESOLVABLE, $layoutId)], $context);

        $this->repository()->update([['id' => $layoutId, 'name' => 'renamed-layout', 'layout' => $this->tree(TestElementTypeLoader::RESOLVABLE)]], $context);

        $layout = $this->repository()->search(new Criteria([$layoutId]), $context)->getEntities()->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);
        static::assertSame('renamed-layout', $layout->getName());
    }

    #[TestDox('bypasses every check when the write context carries the skip flag')]
    public function testSkipFlagBypassesGate(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(LayoutGate::SKIP_VALIDATION_STATE);
        $id = $this->ids->get('layout');

        $this->repository()->create([$this->layout('category', 'Sw:Test:DefinitelyUnregistered', $id)], $context);

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

    #[TestDox('rejects a layout whose element carries the reserved virtual-root id and stores no row')]
    public function testRejectsReservedElementIdOnWrite(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->ids->get('layout');

        $payload = $this->layout('category', TestElementTypeLoader::RESOLVABLE, $layoutId);
        $payload['layout'] = [
            ['id' => VirtualRootWrapper::VIRTUAL_ROOT_ID, 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => []],
        ];

        try {
            $this->repository()->create([$payload], $context);
            static::fail('Expected the decode gate to reject the reserved virtual-root id.');
        } catch (WriteException $exception) {
            static::assertSame(ContentSystemException::INVALID_ELEMENT_ID, iterator_to_array($exception->getErrors(), false)[0]['code']);
        }

        static::assertNull($this->repository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
    }

    /**
     * The element-local wiring rules, rejected on the write rather than at the first render: the codec throws
     * inside the layout field's normalize, which remaps it onto the write as a constraint violation carrying
     * the codec's own error code, so the caller sees a 400 and no row is stored.
     *
     * @param array<string, mixed> $wiring
     */
    #[DataProvider('elementLocalWiringDefectProvider')]
    #[TestDox('rejects a layout carrying $_dataName and stores no row')]
    public function testRejectsAnElementLocalWiringDefectOnWrite(array $wiring, string $expectedErrorCode): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->ids->get('layout');

        $payload = $this->layout('category', TestElementTypeLoader::RESOLVABLE, $layoutId);
        $payload['layout'] = [
            [
                'id' => $this->ids->get('element'),
                'component' => TestElementTypeLoader::RESOLVABLE,
                'properties' => [],
                ...$wiring,
            ],
        ];

        try {
            $this->repository()->create([$payload], $context);
            static::fail('Expected the write to reject the element-local wiring defect.');
        } catch (WriteException $exception) {
            static::assertSame($expectedErrorCode, iterator_to_array($exception->getErrors(), false)[0]['code']);
        }

        static::assertNull($this->repository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
    }

    #[TestDox('rejects an edit that makes the layout unresolvable for its committed root source')]
    public function testRejectsEditBreakingResolvability(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->ids->get('layout');
        $this->repository()->create([$this->layout('category', TestElementTypeLoader::RESOLVABLE, $layoutId)], $context);

        try {
            $this->repository()->update([['id' => $layoutId, 'layout' => $this->tree(TestElementTypeLoader::UNRESOLVABLE)]], $context);
            static::fail('Expected the gate to re-validate the edit against the committed root source.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Required property "target" is not deterministically resolvable', $exception->getMessage());
        }
    }

    #[TestDox('rejects an update that changes the immutable root source and leaves the stored value unchanged')]
    public function testRejectsRootSourceChange(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->ids->get('layout');
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
        $persisted = $this->repository()->search(new Criteria([$layoutId]), $context)->getEntities()->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $persisted);
        static::assertSame('category', $persisted->getRootSource());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function elementLocalWiringDefectProvider(): iterable
    {
        yield 'two consumers sharing one base key' => [
            ['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => false],
                'category' => ['type' => 'single', 'required' => false, 'propertyAlias' => 'product'],
            ]],
            ContentSystemException::PROPERTY_ALIAS_COLLISION,
        ];

        yield 'a redistributing consumer keyed by a dotted path' => [
            ['acceptsContext' => [
                'product.manufacturer' => ['type' => 'single', 'required' => false, 'redistribute' => true],
            ]],
            ContentSystemException::REDISTRIBUTE_DOTTED_PATH,
        ];

        yield 'a redistributing consumer whose derived key an authored provider holds' => [
            [
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => ['product' => ['type' => 'single', 'required' => false, 'redistribute' => true]],
            ],
            ContentSystemException::REDISTRIBUTE_CONFLICT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function layout(string $rootSource, string $component, ?string $id = null): array
    {
        return [
            'id' => $id ?? $this->ids->get('layout'),
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
            ['id' => $this->ids->get('element'), 'component' => $component, 'properties' => []],
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
