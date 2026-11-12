<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ProviderDeliveryKeyResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\ElementTreePruner;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\Output\SubTreeExtractor;
use Shopware\Core\Framework\ContentSystem\RenderableLayout;
use Shopware\Core\Framework\ContentSystem\Rendering\WiringPlanner;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * The wiring step of the render path, moved out of ContentPipeline: the structural wiring validation
 * and the redistribute derivation, on the planner that owns them now.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(WiringPlanner::class)]
class WiringPlannerTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    /**
     * The derived provider is serialized verbatim into a full-format response, so its distribution config
     * is wire-visible. A derivation that always carried an alias would rename nothing yet still change the
     * body of every plain redistribution.
     */
    #[DataProvider('serializesItsConsumerAliasProvider')]
    #[TestDox('serializes a derived redistribute provider carrying an alias only where the key is renamed')]
    public function testDerivedRedistributeProviderSerializesItsConsumerAlias(?string $consumerAlias, ?string $expectedSerializedAlias): void
    {
        $middle = StoredElementBuilder::create('section', 'middle-id')
            ->withConsumer('featuredProduct', ContextType::Single, redistribute: true, consumerAlias: $consumerAlias)
            ->withSlot('default', [
                StoredElementBuilder::create('text', 'grandchild-id')
                    ->withConsumer($consumerAlias ?? 'featuredProduct', ContextType::Single)
                    ->build(),
            ])
            ->build();
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withProvider('featuredProduct', BroadcastDistributionConfig::simple())
                ->withProperty('featuredProduct', 'product-payload')
                ->withSlot('default', [$middle])
                ->build()
        );

        $derived = $this->planner()->plan($layout->elements, $layout->elements);

        $serialized = $derived[0]->slots['default'][0]->jsonSerialize();

        static::assertSame(
            ['type' => 'single', 'distribution' => 'broadcast', 'consumerAlias' => $expectedSerializedAlias],
            $serialized['providesContext']['featuredProduct']
        );
    }

    #[TestDox('validates a subtree the partial prune is about to discard')]
    public function testRedistributeExpansionValidatesASubtreeThePartialRenderDiscards(): void
    {
        $target = StoredElementBuilder::create('text', 'target-id')->build();
        $discarded = StoredElementBuilder::create('text', 'discarded-id')
            ->withConsumer('product.manufacturer', ContextType::Single, redistribute: true)
            ->build();
        $forest = [
            StoredElementBuilder::create('section', 'root-id')
                ->withSlot('default', [$target, $discarded])
                ->build(),
        ];

        $pruned = (new PartialRenderer(new ElementTreePruner(), new ContextDependencyAnalyzer(), new SubTreeExtractor()))
            ->pruneToTarget($forest, 'target-id');

        // Fixture guard: the real pruner keeps only the target. The target has no consumers, so it is
        // its own data root and the root wrapper is pruned away with the discarded sibling.
        static::assertSame([$target], $pruned);

        $this->expectExceptionObject(ContentSystemException::redistributeWithDottedPath('product.manufacturer'));

        $this->planner()->plan($forest, $pruned);
    }

    #[TestDox('rejects a redistributing consumer whose context key is a dotted path')]
    public function testRedistributeExpansionRejectsADottedConsumerKey(): void
    {
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withConsumer('product.manufacturer', ContextType::Single, redistribute: true)
                ->build()
        );

        $this->expectExceptionObject(ContentSystemException::redistributeWithDottedPath('product.manufacturer'));

        $this->planner()->plan($layout->elements, $layout->elements);
    }

    #[TestDox('rejects a redistributing consumer whose derived provider key is already provided')]
    public function testRedistributeExpansionRejectsAProviderKeyConflict(): void
    {
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withConsumer('product', ContextType::Single, redistribute: true)
                ->withProvider('product', BroadcastDistributionConfig::simple())
                ->build()
        );

        $this->expectExceptionObject(ContentSystemException::redistributeConflict('product'));

        $this->planner()->plan($layout->elements, $layout->elements);
    }

    /**
     * The codec rejects this combination on every DAL path, so the planner is what stands between a tree that
     * bypassed the write boundary (a migration, raw SQL) and a derivation that would mint a provider for a
     * consumer whose value never travels down a chain.
     */
    #[TestDox('rejects a root-scoped consumer that also redistributes')]
    public function testRedistributeExpansionRejectsARootScopedRedistributingConsumer(): void
    {
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withConsumer('product', ContextType::Single, redistribute: true, scope: ConsumerScope::Root)
                ->build()
        );

        $this->expectExceptionObject(ContentSystemException::rootScopeWithRedistribute('product'));

        $this->planner()->plan($layout->elements, $layout->elements);
    }

    /**
     * The scope rule runs ahead of the two derived-key rules inside the redistribute loop, matching decode,
     * where the per-consumer combination tier finishes before the element-local tier starts. This consumer
     * breaks both rules at once, so the exception says which one the planner reached first.
     */
    #[TestDox('reports the root scope before the dotted key for a consumer breaking both rules')]
    public function testRedistributeExpansionReportsTheRootScopeBeforeTheDottedKey(): void
    {
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withConsumer('product.manufacturer', ContextType::Single, redistribute: true, scope: ConsumerScope::Root)
                ->build()
        );

        $this->expectExceptionObject(ContentSystemException::rootScopeWithRedistribute('product.manufacturer'));

        $this->planner()->plan($layout->elements, $layout->elements);
    }

    /**
     * The sibling one edit away on the tested axis: without `redistribute` the scope is none of the rule's
     * business, and the derivation mints nothing for a consumer that hands nothing on.
     */
    #[TestDox('plans a root-scoped consumer that does not redistribute and derives no provider for it')]
    public function testRedistributeExpansionAcceptsARootScopedConsumerThatDoesNotRedistribute(): void
    {
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withConsumer('product', ContextType::Single, scope: ConsumerScope::Root)
                ->build()
        );

        $derived = $this->planner()->plan($layout->elements, $layout->elements);

        static::assertSame([], $derived[0]->contextDefinitions->getAllProviders());
    }

    #[TestDox('rejects two consumers on one element that write the same property key')]
    public function testRedistributeExpansionRejectsAPropertyAliasCollision(): void
    {
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withConsumer('product', ContextType::Single, propertyAlias: 'item')
                ->withConsumer('category', ContextType::Single, propertyAlias: 'item.name')
                ->build()
        );

        $this->expectExceptionObject(ContentSystemException::propertyAliasCollision('item', 'product', 'category'));

        $this->planner()->plan($layout->elements, $layout->elements);
    }

    /**
     * The collision rule itself belongs to ProviderDeliveryKeyResolver and is pinned there per axis. What the
     * planner adds is that every element of the forest is routed through the resolver, nested ones included,
     * each carrying its own id into the exception. The expected element id per row is what pins that.
     */
    #[DataProvider('collidesOnAChildFacingKeyProvider')]
    #[TestDox('rejects a child-facing key collision on any element of the forest and names that element')]
    public function testRejectsAProviderDeliveryCollisionNamingTheOffendingElement(StoredElement $root, string $expectedElementId): void
    {
        $layout = $this->createSingleRootLayout($root);

        try {
            $this->planner()->plan($layout->elements, $layout->elements);
            static::fail('Expected a provider delivery collision.');
        } catch (ContentSystemException $exception) {
            $this->assertProviderDeliveryCollision($exception, 'item', 'product', 'category', $expectedElementId);
        }
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function serializesItsConsumerAliasProvider(): iterable
    {
        yield 'no alias keeps the plain config' => [null, null];
        yield 'alias is carried through' => ['product', 'product'];
    }

    /**
     * @return iterable<string, array{StoredElement, string}>
     */
    public static function collidesOnAChildFacingKeyProvider(): iterable
    {
        // Collision axis: distinct provider map keys, equal child-facing keys — each provider's broadcast
        // config renames what children match on, so the distributor would deliver both to the same child.
        yield 'two authored providers sharing a consumer alias' => [
            StoredElementBuilder::create('section', 'root-id')
                ->withProvider('product', BroadcastDistributionConfig::aliased('item'))
                ->withProvider('category', BroadcastDistributionConfig::aliased('item'))
                ->build(),
            'root-id',
        ];

        // Collision axis: the authored provider's alias and the redistribute consumer's derived child-facing
        // key are equal, while the derived provider's own map key ('category') would not collide.
        yield 'an authored provider and a derived provider' => [
            StoredElementBuilder::create('section', 'root-id')
                ->withProvider('product', BroadcastDistributionConfig::aliased('item'))
                ->withConsumer('category', ContextType::Single, redistribute: true, consumerAlias: 'item')
                ->build(),
            'root-id',
        ];

        // Collision axis: the two consumers write different properties (propertyAlias), so a check judged on
        // the derived provider map key would pass; the corrected formula (consumerAlias ?? contextKey) makes
        // both deliver under 'item' and the layout is rejected.
        yield 'two derived providers sharing a consumer alias' => [
            StoredElementBuilder::create('section', 'root-id')
                ->withConsumer('product', ContextType::Single, redistribute: true, consumerAlias: 'item', propertyAlias: 'productName')
                ->withConsumer('category', ContextType::Single, redistribute: true, consumerAlias: 'item', propertyAlias: 'categoryName')
                ->build(),
            'root-id',
        ];

        // Nesting axis: the clean root is not the offender. The validation descends into the slot and the
        // nested element's own id reaches the exception, so a planner that stopped at the roots or handed
        // the resolver anything but the element it is judging fails here.
        yield 'a nested element whose two authored providers share a consumer alias' => [
            StoredElementBuilder::create('section', 'root-id')
                ->withSlot('default', [
                    StoredElementBuilder::create('section', 'nested-id')
                        ->withProvider('product', BroadcastDistributionConfig::aliased('item'))
                        ->withProvider('category', BroadcastDistributionConfig::aliased('item'))
                        ->build(),
                ])
                ->build(),
            'nested-id',
        ];
    }

    /**
     * Pins the error code explicitly: expectExceptionObject() alone could not — Symfony's HttpException
     * leaves getCode() at 0, so the error code this file's other tests' pattern relies on via the object
     * comparison is not what distinguishes one ContentSystemException from another.
     */
    private function assertProviderDeliveryCollision(
        ContentSystemException $exception,
        string $childKey,
        string $first,
        string $second,
        string $elementId
    ): void {
        static::assertSame(ContentSystemException::PROVIDER_DELIVERY_COLLISION, $exception->getErrorCode());
        static::assertSame($childKey, $exception->getParameter('childKey'));
        static::assertSame($first, $exception->getParameter('first'));
        static::assertSame($second, $exception->getParameter('second'));
        static::assertSame($elementId, $exception->getParameter('elementId'));
    }

    private function planner(): WiringPlanner
    {
        return new WiringPlanner(new ProviderDeliveryKeyResolver());
    }

    private function createSingleRootLayout(StoredElement $root): RenderableLayout
    {
        return RenderableLayout::create(
            LayoutReference::create($this->ids->get('layout'), 'Single Root Layout', '1.0'),
            [$root]
        );
    }
}
