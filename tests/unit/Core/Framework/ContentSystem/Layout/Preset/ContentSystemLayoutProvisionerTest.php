<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\ContentSystemLayoutProvisioner;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\AbstractContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemLayoutProvisioner::class)]
class ContentSystemLayoutProvisionerTest extends TestCase
{
    /**
     * @var StaticEntityRepository<ContentLayoutCollection>
     */
    private StaticEntityRepository $layoutRepository;

    /**
     * @var StaticEntityRepository<ProductContentLayoutCollection>
     */
    private StaticEntityRepository $assignmentRepository;

    protected function setUp(): void
    {
        $this->layoutRepository = StaticEntityRepository::of(ContentLayoutCollection::class);
        $this->assignmentRepository = StaticEntityRepository::of(ProductContentLayoutCollection::class);
    }

    #[TestDox('creates a named layout for the root source from the mutated preset tree')]
    public function testCreatesLayout(): void
    {
        $layoutId = $this->provisioner()->provisionFromPreset('Product page', 'Sw:ProductDetailPage', 'product', Context::createDefaultContext());

        static::assertCount(1, $this->layoutRepository->upserts);
        $layout = $this->layoutRepository->upserts[0][0];
        static::assertSame($layoutId, $layout['id']);
        static::assertSame('Product page', $layout['name']);
        static::assertSame('1.0.0', $layout['version']);
        static::assertSame('product', $layout['rootSource']);
        static::assertSame(['el-1'], array_column($layout['layout'], 'id'));
        static::assertSame([], $this->assignmentRepository->creates);
    }

    #[TestDox('replaces the elements of an existing layout, writing the given name, version and its root source')]
    public function testReplacesExistingLayout(): void
    {
        $layoutId = $this->provisioner()->provisionFromPreset('Renamed', 'Sw:ProductDetailPage', 'product', Context::createDefaultContext(), 'existing-layout', version: '2.0.0');

        static::assertSame('existing-layout', $layoutId);
        $layout = $this->layoutRepository->upserts[0][0];
        static::assertSame('existing-layout', $layout['id']);
        static::assertSame('Renamed', $layout['name']);
        static::assertSame('2.0.0', $layout['version']);
        static::assertSame('product', $layout['rootSource']);
        static::assertSame(['el-1'], array_column($layout['layout'], 'id'));
    }

    #[TestDox('assigns the layout to the entity for all sales channels through the assignment definition of the root source')]
    public function testAssignsLayoutToEntity(): void
    {
        $layoutId = $this->provisioner()->provisionFromPreset('Product page', 'Sw:ProductDetailPage', 'product', Context::createDefaultContext(), entityId: 'product-1');

        static::assertCount(1, $this->assignmentRepository->creates);
        $assignment = $this->assignmentRepository->creates[0][0];
        static::assertSame('product-1', $assignment['productId']);
        static::assertNull($assignment['salesChannelId']);
        static::assertSame($layoutId, $assignment['contentLayoutId']);
    }

    #[TestDox('rejects an entity id for a root source without an assignment definition before writing')]
    public function testRejectsEntityIdForNonAssignableRootSource(): void
    {
        try {
            $this->provisioner()->provisionFromPreset('Header', 'Sw:Header', 'header', Context::createDefaultContext(), entityId: 'entity-1');
            static::fail('Expected the non-assignable root source to be rejected.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::UNKNOWN_ENTITY_TYPE, $exception->getErrorCode());
        }

        static::assertSame([], $this->layoutRepository->upserts);
    }

    private function provisioner(): ContentSystemLayoutProvisioner
    {
        $presets = static::createStub(AbstractContentSystemLayoutPresetRegistry::class);
        $presets->method('get')->willReturn(new ContentSystemLayoutPresetSpecification('Sw:ProductDetailPage', 'Product detail page', null, null, []));

        $pipeline = static::createStub(MutationPipeline::class);
        $pipeline->method('run')->willReturnCallback(static fn (mixed $mutation, StoredTree $tree): MutationResult => MutationResult::fromParts(
            new StoredTree([new StoredElement('el-1', 'Sw:Content:Text')], $tree->rootSource),
            [],
            new DiagnosticsReport([]),
            [],
        ));

        $assignment = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $assignment->method('getContentLayoutEntityType')->willReturn('product');
        $assignment->method('getEntityName')->willReturn('product_content_layout');
        $assignment->method('getContentLayoutEntityIdField')->willReturn('productId');

        $definitions = static::createStub(DefinitionInstanceRegistry::class);
        $definitions->method('getDefinitions')->willReturn(['product_content_layout' => $assignment]);
        $definitions->method('getRepository')->willReturn($this->assignmentRepository);

        return new ContentSystemLayoutProvisioner(
            $presets,
            static::createStub(DraftLayoutDecoder::class),
            $pipeline,
            static::createStub(AbstractContentSystemElementTypeRegistry::class),
            static::createStub(AbstractContentSystemBindingSpecificationRegistry::class),
            new BindingApplicator(static::createStub(DataLoaderConfigSerializerProvider::class), static::createStub(AbstractContentSystemElementTypeRegistry::class)),
            static::createStub(RootSourceRegistry::class),
            new StoredElementCodec(static::createStub(DataLoaderConfigSerializerProvider::class)),
            $this->layoutRepository,
            $definitions,
        );
    }
}
