<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
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
    #[DataProvider('derivedProviderWireShapeProvider')]
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

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function derivedProviderWireShapeProvider(): iterable
    {
        yield 'no alias keeps the plain config' => [null, null];
        yield 'alias is carried through' => ['product', 'product'];
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

    #[TestDox('rejects two authored providers that deliver under the same child-facing key via their shared consumer alias')]
    public function testRejectsAuthoredProvidersSharingAConsumerAlias(): void
    {
        // Collision axis: distinct provider map keys, equal child-facing keys — each provider's broadcast
        // config renames what children match on, so the distributor would deliver both to the same child.
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withProvider('product', BroadcastDistributionConfig::aliased('item'))
                ->withProvider('category', BroadcastDistributionConfig::aliased('item'))
                ->build()
        );

        try {
            $this->planner()->plan($layout->elements, $layout->elements);
            static::fail('Expected a provider delivery collision.');
        } catch (ContentSystemException $exception) {
            $this->assertProviderDeliveryCollision($exception, 'item', 'product', 'category');
        }
    }

    #[TestDox('rejects an authored provider and a redistribute consumer that deliver under the same child-facing key')]
    public function testRejectsAuthoredProviderCollidingWithADerivedProvider(): void
    {
        // Collision axis: the authored provider's alias and the redistribute consumer's derived child-facing
        // key are equal, while the derived provider's own map key ('category') would not collide.
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withProvider('product', BroadcastDistributionConfig::aliased('item'))
                ->withConsumer('category', ContextType::Single, redistribute: true, consumerAlias: 'item')
                ->build()
        );

        try {
            $this->planner()->plan($layout->elements, $layout->elements);
            static::fail('Expected a provider delivery collision.');
        } catch (ContentSystemException $exception) {
            $this->assertProviderDeliveryCollision($exception, 'item', 'product', 'category');
        }
    }

    #[TestDox('rejects two redistribute consumers whose derived child-facing keys collide via their shared consumer alias')]
    public function testRejectsDerivedProvidersSharingAConsumerAlias(): void
    {
        // Collision axis: the two consumers write different properties (propertyAlias), so a check judged on
        // the derived provider map key would pass; the corrected formula (consumerAlias ?? contextKey) makes
        // both deliver under 'item' and the layout is rejected.
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withConsumer('product', ContextType::Single, redistribute: true, consumerAlias: 'item', propertyAlias: 'productName')
                ->withConsumer('category', ContextType::Single, redistribute: true, consumerAlias: 'item', propertyAlias: 'categoryName')
                ->build()
        );

        try {
            $this->planner()->plan($layout->elements, $layout->elements);
            static::fail('Expected a provider delivery collision.');
        } catch (ContentSystemException $exception) {
            $this->assertProviderDeliveryCollision($exception, 'item', 'product', 'category');
        }
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

    /**
     * Pins the error code explicitly: expectExceptionObject() alone could not — Symfony's HttpException
     * leaves getCode() at 0, so the error code this file's other tests' pattern relies on via the object
     * comparison is not what distinguishes one ContentSystemException from another.
     */
    private function assertProviderDeliveryCollision(ContentSystemException $exception, string $childKey, string $first, string $second): void
    {
        static::assertSame(ContentSystemException::PROVIDER_DELIVERY_COLLISION, $exception->getErrorCode());
        static::assertSame($childKey, $exception->getParameter('childKey'));
        static::assertSame($first, $exception->getParameter('first'));
        static::assertSame($second, $exception->getParameter('second'));
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
