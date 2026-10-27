<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteBoundary;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTreeStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationResult;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\BindElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Framework\ContentSystem\Mutation\PropertyMappingMutationFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
class RootSourceMutationSequenceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MutationPipeline $pipeline;

    private PropertyMappingMutationFactory $mappingMutations;

    private AbstractContentSystemElementTypeRegistry $types;

    private AbstractContentSystemBindingSpecificationRegistry $bindings;

    private BindingApplicator $bindingApplicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = static::getContainer()->get(MutationPipeline::class);
        $this->mappingMutations = static::getContainer()->get(PropertyMappingMutationFactory::class);
        $this->types = static::getContainer()->get(ContentSystemElementTypeRegistry::class);
        $this->bindings = static::getContainer()->get(ContentSystemBindingSpecificationRegistry::class);
        $this->bindingApplicator = static::getContainer()->get(BindingApplicator::class);
    }

    public function testRootSourceSurvivesStructuralBindingAndMappingEdits(): void
    {
        $tree = new StoredTree([new StoredElement('container', 'Sw:Grid:Container')], 'product');
        $inserted = $this->mutateTree(new InsertElement(
            $this->types,
            'Sw:Product:PriceDisplay',
            $this->bindings,
            $this->bindingApplicator,
            parentElementId: 'container',
            slot: 'content',
        ), $tree);
        $elementId = $inserted->affectedElementIds[0];
        $tree = $inserted->layout;
        static::assertSame('product', $tree->rootSource);
        static::assertSame('product', $tree->find($elementId)?->contextDefinitions->getAllConsumers()['product']->source?->displayName());

        $tree = static::getContainer()->get(StoredTreeStyleNormalizer::class)->normalize($tree);
        $tree = static::getContainer()->get(LayoutWriteBoundary::class)->apply($tree);
        static::assertSame('product', $tree->rootSource);

        $replaced = $this->mutateTree($this->replace($elementId), $tree);
        $tree = $replaced->layout;
        static::assertSame('product', $tree->rootSource);
        static::assertSame('product', $tree->find($elementId)?->contextDefinitions->getAllConsumers()['product']->source?->displayName());
        static::assertNotContains('product', $replaced->droppedWiring);

        $bound = $this->mutateTree(new BindElement($this->bindings, 'core:Sw:Product:Manufacturer', $elementId, $this->bindingApplicator), $tree);
        static::assertSame('product', $bound->layout->rootSource);
        static::assertSame('core:Sw:Product:Manufacturer', $bound->layout->find($elementId)?->attributedSpecifications['product']);
        $mapped = $this->mutateTree($this->mappingMutations->map($elementId, 'product', MappingSourceReference::root('product')), $bound->layout);
        $duplicated = $this->mutateTree(new DuplicateElement($elementId), $mapped->layout);
        $copyId = $duplicated->affectedElementIds[0];

        $unmapped = $this->mutateTree($this->mappingMutations->unmap($elementId, 'product'), $duplicated->layout);
        $moved = $this->mutateTree(new MoveElement($elementId), $unmapped->layout);
        $tree = static::getContainer()->get(LayoutWriteBoundary::class)->apply($moved->layout);

        static::assertSame('product', $tree->rootSource);
        static::assertArrayNotHasKey('product', $tree->find($elementId)?->contextDefinitions->getAllConsumers() ?? []);
        static::assertSame('product', $tree->find($copyId)?->contextDefinitions->getAllConsumers()['product']->source?->displayName());
    }

    public function testCategoryTreeDoesNotRetainOrSeedAProductOnlyMapping(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'price')
            ->withConsumer('product', ContextType::Single, scope: ConsumerScope::Root, source: MappingSourceReference::root('product'))
            ->build();
        $replaced = $this->mutateTree($this->replace($element->id), new StoredTree([$element], 'category'));

        static::assertSame('category', $replaced->layout->rootSource);
        static::assertContains('product', $replaced->droppedWiring);
        static::assertArrayNotHasKey('product', $replaced->layout->roots[0]->contextDefinitions->getAllConsumers());

        $this->expectExceptionObject(ContentSystemException::unknownMappingPath('product', 'category'));

        $this->mutateTree($this->mappingMutations->map('price', 'product', MappingSourceReference::root('product')), $replaced->layout);
    }

    public function testUnboundDraftRemainsUnboundAfterScaffoldingAndNormalization(): void
    {
        $inserted = $this->mutateTree(new InsertElement($this->types, 'Sw:Product:PriceDisplay', $this->bindings, $this->bindingApplicator), new StoredTree([]));
        $tree = static::getContainer()->get(StoredTreeStyleNormalizer::class)->normalize($inserted->layout);
        $tree = static::getContainer()->get(LayoutWriteBoundary::class)->apply($tree);
        static::assertNull($tree->rootSource);
        static::assertSame([], $tree->roots[0]->contextDefinitions->getAllConsumers());

        $this->expectExceptionObject(ContentSystemException::unknownRootSource(''));

        $this->mutateTree($this->mappingMutations->map($tree->roots[0]->id, 'product', MappingSourceReference::root('product')), $tree);
    }

    private function replace(string $elementId): ReplaceElement
    {
        return new ReplaceElement($this->types, $elementId, 'Sw:Product:Manufacturer', $this->bindings, $this->bindingApplicator, static::getContainer()->get(StoredMappingInspector::class));
    }

    private function mutateTree(LayoutMutation $mutation, StoredTree $tree): MutationResult
    {
        $rootContext = static::getContainer()->get(RootSourceRegistry::class)->resolveGated($tree->rootSource, Context::createDefaultContext());

        return $this->pipeline->run($mutation, $tree, $rootContext);
    }
}
