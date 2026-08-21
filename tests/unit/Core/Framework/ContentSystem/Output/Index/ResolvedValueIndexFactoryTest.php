<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Output\Index\LoaderValueIdentity;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndexFactory;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueFingerprinter;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueOrigin;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueProvenance;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\RenderedElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubExtractorEntity;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;

/**
 * `Sw:Text` declares its three primitives in the order `zulu`, `mike`, `alpha` — the exact reverse of their
 * byte order — so an assertion about type-spec order cannot pass by accident under byte order, and neither
 * can pass by accident under the order a fixture happened to write the keys or the provenance in.
 *
 * No test asserts a literal ref id. Refs are response-local, so what the tests pin is which keys share a ref,
 * which do not, and the order refs are minted in — never the number a ref happens to carry.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResolvedValueIndexFactory::class)]
class ResolvedValueIndexFactoryTest extends TestCase
{
    #[TestDox('walks pre-order: an element, then its slots in map order, then each slot child in list order')]
    public function testTraversalIsPreOrderDepthFirst(): void
    {
        $grandchild = RenderedElementBuilder::create('Sw:Tile', 'grandchild')->withProperty('label', 'g')->build();
        $firstChild = RenderedElementBuilder::create('Sw:Tile', 'child-a')
            ->withProperty('label', 'a')
            ->withSlot('inner', [$grandchild])
            ->build();
        $secondChild = RenderedElementBuilder::create('Sw:Tile', 'child-b')->withProperty('label', 'b')->build();
        $asideChild = RenderedElementBuilder::create('Sw:Tile', 'child-c')->withProperty('label', 'c')->build();
        $root = RenderedElementBuilder::create('Sw:Tile', 'root')
            ->withProperty('label', 'root')
            ->withSlot('main', [$firstChild, $secondChild])
            ->withSlot('aside', [$asideChild])
            ->build();

        $index = $this->factory()->create([$root], $this->declaredPrimitiveFor(
            ['root', 'child-a', 'child-b', 'child-c', 'grandchild'],
            'label'
        ));

        static::assertSame(
            ['root', 'child-a', 'grandchild', 'child-b', 'child-c'],
            array_keys($index->assignments())
        );
        static::assertSame(['root', 'a', 'g', 'b', 'c'], array_values($index->data()));
    }

    #[TestDox('an element with no rendered property gets no assignment entry at all')]
    public function testElementWithoutPropertiesIsAbsentFromTheAssignments(): void
    {
        $child = RenderedElementBuilder::create('Sw:Tile', 'child')->withProperty('label', 'c')->build();
        $root = RenderedElementBuilder::create('Sw:Tile', 'root')->withSlot('main', [$child])->build();

        $index = $this->factory()->create([$root], $this->declaredPrimitiveFor(['child'], 'label'));

        static::assertSame(['child'], array_keys($index->assignments()));
    }

    /**
     * The grammar in one element: declared primitives in the order the type declares them, then the other
     * four categories in byte order of the key name.
     *
     * Three orders are in play for the declared primitives and all three differ, which is what makes the
     * assertion mean something. The type declares `zulu`, `mike`, `alpha`; byte order is the reverse of that;
     * the element's property map writes them `alpha`, `zulu`, `mike`, and the provenance map a fourth way. Only
     * type-spec order produces the expected sequence — map order, provenance order and byte order each fail it.
     */
    #[TestDox('emits declared primitives in type-spec order, then the other four categories in byte order')]
    public function testPerElementEmissionOrderFollowsTheFiveCategories(): void
    {
        $element = RenderedElementBuilder::create('Sw:Text', 'element-1')
            ->withProperties([
                'victor' => 'v',
                'alpha' => 'a',
                'delta' => 'd',
                'yankee' => 'y',
                'charlie' => 'c',
                'zulu' => 'z',
                'echo' => 'e',
                'mike' => 'm',
                'whisky' => 'w',
                'bravo' => 'b',
                'xray' => 'x',
            ])
            ->build();

        $index = $this->factory()->create([$element], ['element-1' => [
            'mike' => new ValueProvenance(ValueOrigin::DeclaredPrimitive),
            'zulu' => new ValueProvenance(ValueOrigin::DeclaredPrimitive),
            'alpha' => new ValueProvenance(ValueOrigin::DeclaredPrimitive),
            'yankee' => $this->loaderProvenance('y', inputsHash: 'inputs-yankee'),
            'bravo' => $this->loaderProvenance('b', inputsHash: 'inputs-bravo'),
            'charlie' => new ValueProvenance(ValueOrigin::DeliveredContext),
            'xray' => new ValueProvenance(ValueOrigin::DeliveredContext),
            'whisky' => new ValueProvenance(ValueOrigin::DistributionReferenced),
            'delta' => new ValueProvenance(ValueOrigin::DistributionReferenced),
        ]]);

        static::assertSame(
            ['zulu', 'mike', 'alpha', 'bravo', 'yankee', 'charlie', 'xray', 'delta', 'whisky', 'echo', 'victor'],
            array_keys($index->assignments()['element-1'])
        );
    }

    /**
     * Byte order, not locale collation: an uppercase initial sorts before every lowercase one because `Z` is
     * the lower byte, which is the opposite of what a collating comparison would answer.
     */
    #[TestDox('orders keys of one category by byte value rather than by collation')]
    public function testByteOrderPutsUppercaseKeysFirst(): void
    {
        $element = RenderedElementBuilder::create('Sw:Tile', 'element-1')
            ->withProperties(['apple' => 'a', 'Zebra' => 'z'])
            ->build();

        $index = $this->factory()->create([$element], ['element-1' => [
            'apple' => new ValueProvenance(ValueOrigin::DeliveredContext),
            'Zebra' => new ValueProvenance(ValueOrigin::DeliveredContext),
        ]]);

        static::assertSame(['Zebra', 'apple'], array_keys($index->assignments()['element-1']));
    }

    /**
     * A declared-primitive key the type declares nothing under has no type-spec position to take, which is
     * also the case for every key of a component naming no registered type at all. It follows the declared
     * ones rather than failing the render.
     */
    #[TestDox('declared-primitive keys the type does not declare follow the declared ones in byte order')]
    public function testUndeclaredPrimitiveKeysFollowTheDeclaredOnes(): void
    {
        $element = RenderedElementBuilder::create('Sw:Text', 'element-1')
            ->withProperties(['omega' => 'o', 'alpha' => 'a', 'nova' => 'n', 'zulu' => 'z'])
            ->build();

        $index = $this->factory()->create(
            [$element],
            $this->declaredPrimitiveFor(['element-1'], 'omega', 'alpha', 'nova', 'zulu')
        );

        static::assertSame(
            ['zulu', 'alpha', 'nova', 'omega'],
            array_keys($index->assignments()['element-1'])
        );
    }

    /**
     * The production case for the loader-identity map: two elements loading the same entity get two distinct
     * PHP instances, because the DAL keeps no identity map across `hydrate()` calls. Instance identity alone
     * would mint two refs for one entity, so the identity map is what collapses them.
     */
    #[TestDox('two elements holding distinct instances of the same entity share one ref')]
    public function testSameEntityFromTwoLoadsSharesOneRef(): void
    {
        $first = new StubExtractorEntity('product-1');
        $second = new StubExtractorEntity('product-1');
        static::assertNotSame($first, $second);

        $index = $this->factory()->create(
            [
                RenderedElementBuilder::create('Sw:Tile', 'element-1')->withProperty('product', $first)->build(),
                RenderedElementBuilder::create('Sw:Tile', 'element-2')->withProperty('product', $second)->build(),
            ],
            [
                'element-1' => ['product' => $this->loaderProvenance($first)],
                'element-2' => ['product' => $this->loaderProvenance($second)],
            ]
        );

        $assignments = $index->assignments();
        static::assertSame($assignments['element-1']['product'], $assignments['element-2']['product']);
        static::assertSame([$first], array_values($index->data()));
    }

    /**
     * The identity is source, config and value together, not the value alone. Two loaders can resolve the same
     * entity and mean different things by it — a different projection, a different association set — so the
     * source separates them even when the entity is identical.
     */
    #[TestDox('the same entity under two loader sources gets separate refs, each carrying its own value')]
    public function testSameEntityUnderDifferentSourcesGetsDistinctRefs(): void
    {
        $first = new StubExtractorEntity('product-1');
        $second = new StubExtractorEntity('product-1');

        $index = $this->factory()->create(
            [
                RenderedElementBuilder::create('Sw:Tile', 'element-1')->withProperty('product', $first)->build(),
                RenderedElementBuilder::create('Sw:Tile', 'element-2')->withProperty('product', $second)->build(),
            ],
            [
                'element-1' => ['product' => $this->loaderProvenance($first, source: 'first_loader')],
                'element-2' => ['product' => $this->loaderProvenance($second, source: 'second_loader')],
            ]
        );

        $assignments = $index->assignments();
        static::assertNotSame($assignments['element-1']['product'], $assignments['element-2']['product']);
        static::assertSame($first, $index->value($assignments['element-1']['product']));
        static::assertSame($second, $index->value($assignments['element-2']['product']));
    }

    /**
     * The same for the config: one loader source resolving one entity under two different configs produces two
     * values that only look alike, so the config hash separates them too.
     */
    #[TestDox('the same entity under two loader configs gets separate refs, each carrying its own value')]
    public function testSameEntityUnderDifferentConfigsGetsDistinctRefs(): void
    {
        $first = new StubExtractorEntity('product-1');
        $second = new StubExtractorEntity('product-1');

        $index = $this->factory()->create(
            [
                RenderedElementBuilder::create('Sw:Tile', 'element-1')->withProperty('product', $first)->build(),
                RenderedElementBuilder::create('Sw:Tile', 'element-2')->withProperty('product', $second)->build(),
            ],
            [
                'element-1' => ['product' => $this->loaderProvenance($first, configHash: 'config-a')],
                'element-2' => ['product' => $this->loaderProvenance($second, configHash: 'config-b')],
            ]
        );

        $assignments = $index->assignments();
        static::assertNotSame($assignments['element-1']['product'], $assignments['element-2']['product']);
        static::assertSame($first, $index->value($assignments['element-1']['product']));
        static::assertSame($second, $index->value($assignments['element-2']['product']));
    }

    /**
     * A unique identifier is unique within an entity type, not across types, so two entities of different
     * classes can legitimately carry the same id. Under one source and one configHash the identity has nothing
     * else to separate them: leave the type out of the key and the second element reuses the first's ref, so
     * the response serves it the first element's entity and drops its own value entirely.
     *
     * Not reachable through a shipped loader — the entity loader's config pins the entity name, so one
     * configHash implies one entity type — but the loader contract permits a third-party loader that resolves
     * polymorphic types under one config, and the grammar this replaces carried the apiAlias in its ref id for
     * exactly this reason.
     */
    #[TestDox('two entities of different types sharing one id get separate refs, each carrying its own entity')]
    public function testEntitiesOfDifferentTypesSharingAnIdDoNotCollide(): void
    {
        $id = '01890fbd7c6f7f0e9a1b2c3d4e5f6071';
        $product = new ProductEntity();
        $product->setUniqueIdentifier($id);
        $category = new CategoryEntity();
        $category->setUniqueIdentifier($id);
        static::assertNotSame($product->getApiAlias(), $category->getApiAlias());

        $index = $this->factory()->create(
            [
                RenderedElementBuilder::create('Sw:Tile', 'element-1')->withProperty('subject', $product)->build(),
                RenderedElementBuilder::create('Sw:Tile', 'element-2')->withProperty('subject', $category)->build(),
            ],
            [
                'element-1' => ['subject' => $this->loaderProvenance($product)],
                'element-2' => ['subject' => $this->loaderProvenance($category)],
            ]
        );

        $assignments = $index->assignments();
        $productRef = $assignments['element-1']['subject'];
        $categoryRef = $assignments['element-2']['subject'];

        static::assertNotSame($productRef, $categoryRef);
        static::assertSame($product, $index->value($productRef));
        static::assertSame($category, $index->value($categoryRef));
    }

    /**
     * The same case for a value that is not an entity — a collection, a tree, a listing result. Two loads
     * hand back two instances of it just as they do for an entity, so neither instance identity nor value
     * comparison can collapse them; only source, config and inputs can. This is what the grammar being
     * replaced could not do at all, and what makes `inputsHash` load-bearing rather than decorative.
     */
    #[TestDox('two elements holding distinct instances of an equal non-entity loader value share one ref')]
    public function testSameNonEntityValueFromTwoLoadsSharesOneRef(): void
    {
        $first = new EntityCollection([new StubExtractorEntity('product-1')]);
        $second = new EntityCollection([new StubExtractorEntity('product-1')]);
        static::assertNotSame($first, $second);

        $index = $this->factory()->create(
            [
                RenderedElementBuilder::create('Sw:Tile', 'element-1')->withProperty('products', $first)->build(),
                RenderedElementBuilder::create('Sw:Tile', 'element-2')->withProperty('products', $second)->build(),
            ],
            [
                'element-1' => ['products' => $this->loaderProvenance($first)],
                'element-2' => ['products' => $this->loaderProvenance($second)],
            ]
        );

        $assignments = $index->assignments();
        static::assertSame($assignments['element-1']['products'], $assignments['element-2']['products']);
        static::assertSame([$first], array_values($index->data()));
    }

    /**
     * The counterpart that pins the mechanism rather than the outcome: the two values are equal and neither
     * is the other's instance, so the inputs hash in the key is the only thing separating them. Drop it and
     * these two collapse onto one ref.
     */
    #[TestDox('two elements resolving one loader config against different inputs get distinct refs')]
    public function testSameConfigWithDifferentInputsGetsDistinctRefs(): void
    {
        $first = new EntityCollection([new StubExtractorEntity('product-1')]);
        $second = new EntityCollection([new StubExtractorEntity('product-1')]);

        $index = $this->factory()->create(
            [
                RenderedElementBuilder::create('Sw:Tile', 'element-1')->withProperty('products', $first)->build(),
                RenderedElementBuilder::create('Sw:Tile', 'element-2')->withProperty('products', $second)->build(),
            ],
            [
                'element-1' => ['products' => $this->loaderProvenance($first, inputsHash: 'inputs-a')],
                'element-2' => ['products' => $this->loaderProvenance($second, inputsHash: 'inputs-b')],
            ]
        );

        $assignments = $index->assignments();
        static::assertNotSame($assignments['element-1']['products'], $assignments['element-2']['products']);
        static::assertSame([$first, $second], array_values($index->data()));
    }

    /**
     * A broadcast hands every child the provider's own instance, so the delivered key resolves to the ref the
     * provider's loader-resolved key already has.
     */
    #[TestDox('a delivered value that is the provider instance shares the provider ref')]
    public function testBroadcastDeliverySharesTheProviderRef(): void
    {
        $provided = new StubStruct();
        $child = RenderedElementBuilder::create('Sw:Tile', 'child')->withProperty('product', $provided)->build();
        $parent = RenderedElementBuilder::create('Sw:Tile', 'parent')
            ->withProperty('product', $provided)
            ->withSlot('main', [$child])
            ->build();

        $index = $this->factory()->create([$parent], [
            'parent' => ['product' => $this->loaderProvenance($provided)],
            'child' => ['product' => new ValueProvenance(ValueOrigin::DeliveredContext)],
        ]);

        $assignments = $index->assignments();
        static::assertSame($assignments['parent']['product'], $assignments['child']['product']);
        static::assertSame([$provided], array_values($index->data()));
    }

    #[TestDox('a delivered value that is not the provider instance gets its own ref')]
    public function testTransformedDeliveryGetsItsOwnRef(): void
    {
        $provided = new StubStruct();
        $child = RenderedElementBuilder::create('Sw:Tile', 'child')
            ->withProperty('product', new StubStruct())
            ->build();
        $parent = RenderedElementBuilder::create('Sw:Tile', 'parent')
            ->withProperty('product', $provided)
            ->withSlot('main', [$child])
            ->build();

        $index = $this->factory()->create([$parent], [
            'parent' => ['product' => $this->loaderProvenance($provided)],
            'child' => ['product' => new ValueProvenance(ValueOrigin::DeliveredContext)],
        ]);

        $assignments = $index->assignments();
        static::assertNotSame($assignments['parent']['product'], $assignments['child']['product']);
        static::assertCount(2, $index->data());
    }

    #[TestDox('every loader that found nothing shares one null-valued ref')]
    public function testLoaderNullsShareOneRef(): void
    {
        $index = $this->factory()->create(
            [
                RenderedElementBuilder::create('Sw:Tile', 'element-1')->withProperty('product', null)->build(),
                RenderedElementBuilder::create('Sw:Tile', 'element-2')->withProperty('category', null)->build(),
            ],
            [
                'element-1' => ['product' => $this->loaderProvenance(null, source: 'product')],
                'element-2' => ['category' => $this->loaderProvenance(null, source: 'category')],
            ]
        );

        $assignments = $index->assignments();
        static::assertSame($assignments['element-1']['product'], $assignments['element-2']['category']);
        static::assertSame([null], array_values($index->data()));
    }

    /**
     * Null is the one value whose dedup does not follow its origin. A loader's `notFound()` and an
     * under-supplied context delivery are two different producers of an explicit null, and the response
     * carries the pair as one entry: what separates "a resolution ran and found nothing" from "nothing wrote
     * here" is the presence of the assignment entry, not which null-valued ref it names.
     */
    #[TestDox('a loader notFound() null and a delivered-context null share one ref')]
    public function testEveryNullSharesOneRefWhateverProducedIt(): void
    {
        $element = RenderedElementBuilder::create('Sw:Tile', 'element-1')
            ->withProperties(['product' => null, 'category' => null])
            ->build();

        $index = $this->factory()->create([$element], [
            'element-1' => [
                'product' => $this->loaderProvenance(null),
                'category' => new ValueProvenance(ValueOrigin::DeliveredContext),
            ],
        ]);

        $assignments = $index->assignments()['element-1'];
        static::assertSame($assignments['product'], $assignments['category']);
        static::assertSame([null], array_values($index->data()));
    }

    #[TestDox('one object instance under two categories gets one ref')]
    public function testObjectValuesDedupByInstanceWhateverTheirCategory(): void
    {
        $shared = new StubStruct();

        $index = $this->factory()->create(
            [
                RenderedElementBuilder::create('Sw:Tile', 'element-1')->withProperty('category', $shared)->build(),
                RenderedElementBuilder::create('Sw:Tile', 'element-2')->withProperty('extra', $shared)->build(),
            ],
            ['element-1' => ['category' => new ValueProvenance(ValueOrigin::DeliveredContext)]]
        );

        $assignments = $index->assignments();
        static::assertSame($assignments['element-1']['category'], $assignments['element-2']['extra']);
        static::assertSame([$shared], array_values($index->data()));
    }

    #[DataProvider('valueEqualityProvider')]
    #[TestDox('deduplicates non-object values by value equality')]
    public function testNonObjectValuesDedupByValueEquality(mixed $first, mixed $second, bool $shareOneRef): void
    {
        $index = $this->factory()->create(
            [
                RenderedElementBuilder::create('Sw:Tile', 'element-1')->withProperty('note', $first)->build(),
                RenderedElementBuilder::create('Sw:Tile', 'element-2')->withProperty('note', $second)->build(),
            ],
            []
        );

        $assignments = $index->assignments();
        static::assertSame(
            $shareOneRef,
            $assignments['element-1']['note'] === $assignments['element-2']['note']
        );
    }

    /**
     * A finalization listener writes what it likes onto the rendered element, and nothing records provenance
     * for what it wrote. Such a key is still on the element and its value still has to be served, so it takes
     * the injected category and is emitted after the four the pipeline produces.
     */
    #[TestDox('a key with no provenance entry is injected, emitted last, and resolves to its value')]
    public function testKeyWithoutProvenanceIsInjected(): void
    {
        $element = RenderedElementBuilder::create('Sw:Text', 'element-1')
            ->withProperties(['note' => 'listener wrote this', 'zulu' => 'z'])
            ->build();

        $index = $this->factory()->create([$element], $this->declaredPrimitiveFor(['element-1'], 'zulu'));

        $assignments = $index->assignments()['element-1'];
        static::assertSame(['zulu', 'note'], array_keys($assignments));
        static::assertSame('listener wrote this', $index->value($assignments['note']));
    }

    #[TestDox('ignores a provenance entry for a key the element no longer carries')]
    public function testProvenanceForAnAbsentKeyIsIgnored(): void
    {
        $element = RenderedElementBuilder::create('Sw:Text', 'element-1')->withProperty('zulu', 'z')->build();

        $index = $this->factory()->create([$element], ['element-1' => [
            'zulu' => new ValueProvenance(ValueOrigin::DeclaredPrimitive),
            'ghost' => $this->loaderProvenance('gone'),
        ]]);

        static::assertSame(['zulu'], array_keys($index->assignments()['element-1']));
        static::assertSame(['z'], array_values($index->data()));
    }

    /**
     * Two elements sharing an id would merge their assignments into one entry, so each would serve some of
     * the other's values. A served forest is stored data and the read path validates nothing, so a repeated
     * id reaches here from a raw-SQL write or from a listener that replaced the tree.
     */
    #[TestDox('an element id repeated by one of its own descendants fails the build')]
    public function testRepeatedElementIdOnThePathThrows(): void
    {
        $child = RenderedElementBuilder::create('Sw:Tile', 'element-1')->withProperty('label', 'child')->build();
        $root = RenderedElementBuilder::create('Sw:Tile', 'element-1')
            ->withProperty('label', 'root')
            ->withSlot('main', [$child])
            ->build();

        $this->assertBuildFailsWithDuplicateId([$root], 'element-1');
    }

    /**
     * The uniqueness the assignment map needs is forest-wide, not per branch. Two elements in different slots
     * share no ancestor path, so a guard that only remembered the ids above it would let this through and merge
     * their assignments — the same corruption as the ancestor case, reached the other way.
     */
    #[TestDox('one element id shared by two elements in different slots fails the build')]
    public function testRepeatedElementIdAcrossBranchesThrows(): void
    {
        $inMain = RenderedElementBuilder::create('Sw:Tile', 'twin')->withProperty('label', 'main')->build();
        $inAside = RenderedElementBuilder::create('Sw:Tile', 'twin')->withProperty('label', 'aside')->build();
        $root = RenderedElementBuilder::create('Sw:Tile', 'root')
            ->withProperty('label', 'root')
            ->withSlot('main', [$inMain])
            ->withSlot('aside', [$inAside])
            ->build();

        $this->assertBuildFailsWithDuplicateId([$root], 'twin');
    }

    /**
     * The regression test for letting the identity govern a value the loader no longer owns. Both elements
     * carry one identity, and the second element's rendered value is not what its loader returned — a
     * finalization listener replaced it, which is permitted and leaves the key's category alone. Reusing the
     * first ref would serve the pre-rewrite value under the rewritten key; refusing the write would turn a
     * permitted listener action into a failed render. So the fingerprint mismatch drops it out of the loader
     * rule and it dedups as an ordinary value.
     */
    #[TestDox('a loader-resolved value a listener replaced gets its own ref and keeps its emission slot')]
    public function testReplacedLoaderValueGetsItsOwnRef(): void
    {
        $index = $this->factory()->create(
            [
                RenderedElementBuilder::create('Sw:Text', 'element-1')
                    ->withProperty('product', 'original')
                    ->build(),
                RenderedElementBuilder::create('Sw:Text', 'element-2')
                    ->withProperties(['note' => 'n', 'product' => 'rewritten', 'zulu' => 'z'])
                    ->build(),
            ],
            [
                'element-1' => ['product' => $this->loaderProvenance('original')],
                'element-2' => [
                    'product' => $this->loaderProvenance('original'),
                    'zulu' => new ValueProvenance(ValueOrigin::DeclaredPrimitive),
                ],
            ]
        );

        $assignments = $index->assignments();
        static::assertNotSame($assignments['element-1']['product'], $assignments['element-2']['product']);
        static::assertSame('original', $index->value($assignments['element-1']['product']));
        static::assertSame('rewritten', $index->value($assignments['element-2']['product']));
        static::assertSame(['zulu', 'product', 'note'], array_keys($assignments['element-2']));
    }

    /**
     * The object-branch counterpart to {@see testReplacedLoaderValueGetsItsOwnRef}: the fingerprint mismatch
     * that drops a replaced value out of the loader rule has to fire on `spl_object_id`, not only on
     * `Hasher::hash`, or a producer that drifted onto hashing objects would pass every string-only test in
     * this file while the object branch stayed unguarded.
     *
     * Both objects are held in local variables for the whole test so neither becomes unreachable and risks
     * having its `spl_object_id` recycled onto the other before the assertions run.
     */
    #[TestDox('a loader-resolved object value a listener replaced gets its own ref and keeps its own instance')]
    public function testReplacedLoaderObjectValueGetsItsOwnRef(): void
    {
        $produced = new StubStruct();
        $replacement = new StubStruct();

        $index = $this->factory()->create(
            [
                RenderedElementBuilder::create('Sw:Tile', 'element-1')
                    ->withProperty('product', $produced)
                    ->build(),
                RenderedElementBuilder::create('Sw:Tile', 'element-2')
                    ->withProperty('product', $replacement)
                    ->build(),
            ],
            [
                'element-1' => ['product' => $this->loaderProvenance($produced)],
                'element-2' => ['product' => $this->loaderProvenance($produced)],
            ]
        );

        $assignments = $index->assignments();
        static::assertNotSame($assignments['element-1']['product'], $assignments['element-2']['product']);
        static::assertSame($produced, $index->value($assignments['element-1']['product']));
        static::assertSame($replacement, $index->value($assignments['element-2']['product']));
    }

    /**
     * The identity key alone decides a loader-resolved value's ref, so two keys differing only in their inputs
     * hash stay apart even when their values are equal: one source resolving two inputs to the same string is
     * two resolutions, and collapsing them would serve the second element the first's.
     *
     * Every other distinct-ref test here holds an object, which takes the instance branch and so never reaches
     * the value-equality scan — which is how a loader miss falling through into that scan went unseen.
     *
     * @param string|list<string> $value
     */
    #[DataProvider('equalLoaderValueProvider')]
    #[TestDox('two loader-resolved keys with different identities and equal values get distinct refs')]
    public function testEqualLoaderValuesUnderDifferentIdentitiesGetDistinctRefs(string|array $value): void
    {
        $index = $this->factory()->create(
            [
                RenderedElementBuilder::create('Sw:Tile', 'element-1')->withProperty('teaser', $value)->build(),
                RenderedElementBuilder::create('Sw:Tile', 'element-2')->withProperty('teaser', $value)->build(),
            ],
            [
                'element-1' => ['teaser' => $this->loaderProvenance($value, inputsHash: 'inputs-a')],
                'element-2' => ['teaser' => $this->loaderProvenance($value, inputsHash: 'inputs-b')],
            ]
        );

        $assignments = $index->assignments();
        static::assertNotSame($assignments['element-1']['teaser'], $assignments['element-2']['teaser']);
        static::assertSame($value, $index->value($assignments['element-1']['teaser']));
        static::assertSame($value, $index->value($assignments['element-2']['teaser']));
        static::assertSame([$value, $value], array_values($index->data()));
    }

    /**
     * The two shapes the value-equality scan can answer for: a scalar by `===`, a list positionally. An object
     * has no place here — it cannot reach the scan at all, which is exactly why this case was missing.
     *
     * @return \Generator<string, array{string|list<string>}>
     */
    public static function equalLoaderValueProvider(): \Generator
    {
        yield 'two equal scalars' => ['a teaser headline'];
        yield 'two equal lists' => [['first', 'second']];
    }

    /**
     * @return \Generator<string, array{mixed, mixed, bool}>
     */
    public static function valueEqualityProvider(): \Generator
    {
        yield 'equal strings share one ref' => ['Hello', 'Hello', true];
        yield 'two lists holding the same items in the same order share one ref' => [[1, 2], [1, 2], true];
        yield 'int and its numeric string are different values' => [0, '0', false];
        yield 'false and its int are different values' => [false, 0, false];
        yield 'a map whose keys are in another order is the same value' => [
            ['a' => 1, 'b' => 2],
            ['b' => 2, 'a' => 1],
            true,
        ];
        yield 'a nested map whose keys are in another order is the same value' => [
            ['outer' => ['a' => 1, 'b' => 2]],
            ['outer' => ['b' => 2, 'a' => 1]],
            true,
        ];
        yield 'a list whose items are in another order is a different value' => [[1, 2], [2, 1], false];
        yield 'a list and a map holding the same value are different values' => [['a'], ['x' => 'a'], false];
    }

    /**
     * @param list<RenderedElement> $tree
     */
    private function assertBuildFailsWithDuplicateId(array $tree, string $elementId): void
    {
        try {
            $this->factory()->create($tree, []);
            static::fail('Expected ContentSystemException was not thrown.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::DUPLICATE_ELEMENT_ID, $exception->getErrorCode());
            static::assertStringContainsString(
                \sprintf('element ID "%s" appears more than once', $elementId),
                $exception->getMessage()
            );
        }
    }

    /**
     * @param list<string> $elementIds
     *
     * @return array<string, array<string, ValueProvenance>>
     */
    private function declaredPrimitiveFor(array $elementIds, string ...$keys): array
    {
        $provenance = [];

        foreach ($elementIds as $elementId) {
            foreach ($keys as $key) {
                $provenance[$elementId][$key] = new ValueProvenance(ValueOrigin::DeclaredPrimitive);
            }
        }

        return $provenance;
    }

    /**
     * Stands in for the producer that records provenance at lowering time, and goes through the same
     * {@see ValueFingerprinter} the factory does, which is the whole reason that rule is a collaborator.
     * Passing a produced value the element does not carry is how a test stages a listener's replacement.
     * A reimplementation of the fingerprint rule is caught here the moment it diverges from
     * {@see ValueFingerprinter}, on both of its branches.
     */
    private function loaderProvenance(
        mixed $producedValue,
        string $source = 'product',
        string $configHash = 'config-a',
        string $inputsHash = 'inputs-a',
    ): ValueProvenance {
        return new ValueProvenance(
            ValueOrigin::LoaderResolved,
            new LoaderValueIdentity(
                $source,
                $configHash,
                $inputsHash,
                (new ValueFingerprinter())->fingerprint($producedValue)
            )
        );
    }

    private function factory(): ResolvedValueIndexFactory
    {
        $specs = [
            'Sw:Text' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Text')
                ->primitive('zulu', 'string')
                ->primitive('mike', 'string')
                ->primitive('alpha', 'string')
                ->build(),
            'Sw:Tile' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Tile')
                ->primitive('label', 'string')
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(
            static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]
        );

        return new ResolvedValueIndexFactory($registry, new ValueFingerprinter());
    }
}
